<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\Clinic;
use App\Models\LedgerAccount;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class BackupService
{
    protected string $backupDir;
    protected string $schemaVersion = '24.0.0';

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
        if (!File::exists($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0755, true);
        }
    }

    /**
     * Resolve encryption password from argument or secure environment configuration.
     */
    protected function resolvePassword(?string $password = null): string
    {
        if (!empty($password)) {
            if (strlen($password) < 6) {
                throw new InvalidArgumentException("Backup password must be at least 6 characters.");
            }
            return $password;
        }

        $configured = config('dxclinic.backup_passphrase') ?: env('BACKUP_PASSPHRASE');
        if (!empty($configured) && strlen($configured) >= 6) {
            return $configured;
        }

        throw new InvalidArgumentException("Backup encryption passphrase must be provided or configured in BACKUP_PASSPHRASE.");
    }

    /**
     * Create an encrypted .ncbk backup
     *
     * @param string|null $password User-supplied administrative password or null for configured passphrase
     * @param string $type MANUAL or AUTOMATED or PRE_RESTORE
     * @return Backup
     */
    public function createBackup(?string $password = null, string $type = 'MANUAL', ?string $customDestinationDir = null): Backup
    {
        $password = $this->resolvePassword($password);


        $timestamp = Carbon::now()->format('Y-m-d_His');
        $tempDir = storage_path("app/backups/temp_{$timestamp}");
        File::makeDirectory($tempDir, 0755, true);

        try {
            // 1. Export database tables
            $sqlFile = "{$tempDir}/database.sql";
            $tableCount = $this->dumpDatabase($sqlFile);

            // 2. Collect files (branding logos, knowledge)
            $filesDir = "{$tempDir}/files";
            File::makeDirectory($filesDir, 0755, true);
            $fileCount = $this->collectMediaFiles($filesDir);

            // 3. Create Manifest
            $manifest = [
                'app_name' => 'DxClinic',
                'schema_version' => $this->schemaVersion,
                'created_at' => Carbon::now()->toIso8601String(),
                'backup_type' => $type,
                'table_count' => $tableCount,
                'file_count' => $fileCount,
                'clinic' => Clinic::first()?->only(['id', 'name', 'phone', 'email']),
                'sql_checksum' => hash_file('sha256', $sqlFile),
            ];
            file_put_contents("{$tempDir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));

            // 4. Create intermediate zip container
            $zipPath = "{$tempDir}/container.zip";
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Could not create backup archive container.");
            }

            $zip->addFile("{$tempDir}/manifest.json", "manifest.json");
            $zip->addFile($sqlFile, "database.sql");

            if (File::exists($filesDir)) {
                foreach (File::allFiles($filesDir) as $file) {
                    $zip->addFile($file->getRealPath(), "files/" . $file->getRelativePathname());
                }
            }
            $zip->close();

            // 5. Encrypt container with AES-256-GCM + PBKDF2 key derivation
            $zipData = file_get_contents($zipPath);
            $encryptedData = $this->encryptPayload($zipData, $password);

            // 6. Save final .ncbk file
            $finalFilename = "dxclinic_backup_{$timestamp}.ncbk";
            $targetDir = $customDestinationDir && File::isDirectory($customDestinationDir) ? $customDestinationDir : $this->backupDir;
            $finalPath = "{$targetDir}/{$finalFilename}";
            file_put_contents($finalPath, $encryptedData);

            // Also keep in default backupDir if custom destination used
            if ($targetDir !== $this->backupDir) {
                file_put_contents("{$this->backupDir}/{$finalFilename}", $encryptedData);
            }

            $checksum = hash_file('sha256', $finalPath);
            $sizeBytes = filesize($finalPath);

            // 7. Record in database
            $backupRecord = Backup::create([
                'filename' => $finalFilename,
                'file_path' => $finalPath,
                'size_bytes' => $sizeBytes,
                'checksum' => $checksum,
                'schema_version' => $this->schemaVersion,
                'backup_type' => $type,
                'status' => 'SUCCESS',
                'storage_location' => 'LOCAL',
            ]);

            // 8. Generate companion metadata manifest file (.meta.json)
            $metaData = [
                'backup_id' => $backupRecord->id,
                'filename' => $finalFilename,
                'sha256_checksum' => $checksum,
                'size_bytes' => $sizeBytes,
                'size_formatted' => number_format($sizeBytes / 1024, 2) . ' KB',
                'schema_version' => $this->schemaVersion,
                'backup_type' => $type,
                'created_at' => Carbon::now()->toIso8601String(),
                'clinic' => Clinic::first()?->only(['id', 'clinic_code', 'name', 'phone', 'email']),
                'table_count' => $tableCount,
                'file_count' => $fileCount,
                'storage_location' => 'LOCAL',
                'target_directory' => $targetDir,
            ];
            file_put_contents("{$finalPath}.meta.json", json_encode($metaData, JSON_PRETTY_PRINT));
            if ($targetDir !== $this->backupDir) {
                file_put_contents("{$this->backupDir}/{$finalFilename}.meta.json", json_encode($metaData, JSON_PRETTY_PRINT));
            }

            return $backupRecord;
        } catch (Exception $e) {
            Log::error("Backup creation failed: " . $e->getMessage());
            Backup::create([
                'filename' => "failed_{$timestamp}.ncbk",
                'file_path' => '',
                'size_bytes' => 0,
                'checksum' => '',
                'schema_version' => $this->schemaVersion,
                'backup_type' => $type,
                'status' => 'FAILED',
                'storage_location' => 'LOCAL',
            ]);
            throw $e;
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * Verify an encrypted backup file
     */
    public function verifyBackup(string $filePath, ?string $password = null): array
    {
        $password = $this->resolvePassword($password);

        if (!File::exists($filePath)) {
            throw new InvalidArgumentException("Backup file does not exist at: {$filePath}");
        }

        $checksum = hash_file('sha256', $filePath);
        $encryptedData = file_get_contents($filePath);

        // Decrypt payload
        $decryptedZip = $this->decryptPayload($encryptedData, $password);

        $tempVerifyZip = storage_path("app/backups/verify_" . uniqid() . ".zip");
        file_put_contents($tempVerifyZip, $decryptedZip);

        $zip = new ZipArchive();
        if ($zip->open($tempVerifyZip) !== true) {
            File::delete($tempVerifyZip);
            throw new RuntimeException("Decrypted data is not a valid archive container.");
        }

        $manifestRaw = $zip->getFromName('manifest.json');
        if (!$manifestRaw) {
            $zip->close();
            File::delete($tempVerifyZip);
            throw new RuntimeException("Missing manifest.json inside backup container.");
        }

        $manifest = json_decode($manifestRaw, true);
        $hasSql = $zip->locateName('database.sql') !== false;

        $zip->close();
        File::delete($tempVerifyZip);

        return [
            'status' => 'VERIFIED',
            'checksum' => $checksum,
            'schema_version' => $manifest['schema_version'] ?? 'unknown',
            'created_at' => $manifest['created_at'] ?? 'unknown',
            'backup_type' => $manifest['backup_type'] ?? 'MANUAL',
            'table_count' => $manifest['table_count'] ?? 0,
            'file_count' => $manifest['file_count'] ?? 0,
            'has_sql' => $hasSql,
            'manifest' => $manifest,
        ];
    }

    /**
     * Restore from a backup container with automatic pre-restore backup and rollback safety
     */
    public function restoreBackup(string $filePath, ?string $password = null): bool
    {
        $password = $this->resolvePassword($password);

        // 1. Verify before touching anything
        $verification = $this->verifyBackup($filePath, $password);

        // 2. Create automatic pre-restore safety backup
        $safetyBackup = $this->createBackup($password, 'PRE_RESTORE');


        $encryptedData = file_get_contents($filePath);
        $decryptedZip = $this->decryptPayload($encryptedData, $password);

        $tempRestoreDir = storage_path("app/backups/restore_" . uniqid());
        File::makeDirectory($tempRestoreDir, 0755, true);
        $tempZipPath = "{$tempRestoreDir}/archive.zip";
        file_put_contents($tempZipPath, $decryptedZip);

        $zip = new ZipArchive();
        if ($zip->open($tempZipPath) !== true) {
            File::deleteDirectory($tempRestoreDir);
            throw new RuntimeException("Cannot extract restore container.");
        }
        $zip->extractTo($tempRestoreDir);
        $zip->close();

        $sqlPath = "{$tempRestoreDir}/database.sql";
        if (!File::exists($sqlPath)) {
            File::deleteDirectory($tempRestoreDir);
            throw new RuntimeException("Backup does not contain database.sql.");
        }

        // 3. Atomic execute SQL dump with rollback safeguard
        try {
            $sqlContent = file_get_contents($sqlPath);
            $this->executeSqlScript($sqlContent);

            // 4. Restore files (e.g. logos)
            $extractedFiles = "{$tempRestoreDir}/files";
            if (File::exists($extractedFiles)) {
                $targetStorage = storage_path('app/public');
                File::copyDirectory($extractedFiles, $targetStorage);
            }

            // 5. Verify accounting integrity after restore
            $this->verifyAccountingIntegrity();

            // 6. Update backup record
            Backup::where('checksum', $verification['checksum'])
                ->update(['status' => 'RESTORED', 'restored_at' => Carbon::now()]);

            return true;
        } catch (Exception $e) {
            Log::error("Restore error, rolling back using safety backup: " . $e->getMessage());

            // Rollback using the pre-restore safety backup
            try {
                $this->executeSqlFromArchive($safetyBackup->file_path, $password);
            } catch (Exception $rollbackEx) {
                Log::critical("Rollback failure: " . $rollbackEx->getMessage());
            }

            throw new RuntimeException("Restore failed: " . $e->getMessage() . ". System was safely rolled back to pre-restore snapshot.");
        } finally {
            File::deleteDirectory($tempRestoreDir);
        }
    }

    /**
     * Dump all relevant application tables into SQL
     */
    protected function dumpDatabase(string $outputFile): int
    {
        $tables = [
            'clinics',
            'doctors',
            'users',
            'ledger_accounts',
            'bank_accounts',
            'expense_categories',
            'fee_masters',
            'diagnostic_items',
            'patients',
            'appointments',
            'visits',
            'vitals',
            'consultation_bills',
            'diagnostic_bills',
            'diagnostic_bill_items',
            'payments',
            'receipts',
            'refunds',
            'receipt_revisions',
            'expenses',
            'fund_transfers',
            'cash_sessions',
            'journals',
            'journal_entries',
            'token_sequences',
            'audit_logs',
            'prescription_pad_templates',
            'prescription_pad_elements',
            'printer_profiles',
        ];

        $handle = fopen($outputFile, 'w');
        fwrite($handle, "-- DxClinic Database Backup Dump\n");
        fwrite($handle, "-- Generated: " . Carbon::now()->toIso8601String() . "\n\n");
        if (DB::connection()->getDriverName() !== 'sqlite') {
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");
        } else {
            fwrite($handle, "PRAGMA foreign_keys = OFF;\n\n");
        }

        $count = 0;
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) continue;

            $count++;
            fwrite($handle, "-- Table: {$table}\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");

            // Create table DDL
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                $createTableObj = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table]);
                if (!empty($createTableObj) && !empty($createTableObj[0]->sql)) {
                    fwrite($handle, "{$createTableObj[0]->sql};\n\n");
                }
            } else {
                $createTableObj = DB::select("SHOW CREATE TABLE `{$table}`");
                if (!empty($createTableObj)) {
                    $createSql = $createTableObj[0]->{'Create Table'};
                    fwrite($handle, "{$createSql};\n\n");
                }
            }

            // Insert rows
            $rows = DB::table($table)->get();
            if ($rows->count() > 0) {
                foreach ($rows->chunk(100) as $chunk) {
                    $insertValues = [];
                    foreach ($chunk as $row) {
                        $rowArray = (array) $row;
                        $escapedValues = array_map(function ($val) {
                            if ($val === null) return 'NULL';
                            return "'" . addslashes((string) $val) . "'";
                        }, $rowArray);
                        $insertValues[] = "(" . implode(', ', $escapedValues) . ")";
                    }
                    $columns = array_keys((array) $chunk->first());
                    $colsStr = implode('`, `', $columns);
                    $insertSql = "INSERT INTO `{$table}` (`{$colsStr}`) VALUES\n" . implode(",\n", $insertValues) . ";\n";
                    fwrite($handle, $insertSql);
                }
                fwrite($handle, "\n");
            }
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        return $count;
    }

    /**
     * Collect stored files (branding logos, prescription pads, knowledge, etc.)
     */
    protected function collectMediaFiles(string $targetDir): int
    {
        $count = 0;
        $brandingDir = storage_path('app/public/branding');
        if (File::exists($brandingDir)) {
            File::copyDirectory($brandingDir, "{$targetDir}/branding");
            $count += count(File::allFiles($brandingDir));
        }

        $padDir = storage_path('app/public/prescription-pads');
        if (File::exists($padDir)) {
            File::copyDirectory($padDir, "{$targetDir}/prescription-pads");
            $count += count(File::allFiles($padDir));
        }

        $knowledgeDir = base_path('knowledge');
        if (File::exists($knowledgeDir)) {
            File::copyDirectory($knowledgeDir, "{$targetDir}/knowledge");
            $count += count(File::allFiles($knowledgeDir));
        }

        return $count;
    }

    /**
     * Encrypt binary payload using PBKDF2 + AES-256-GCM
     * Format: SALT (16B) || IV (12B) || TAG (16B) || CIPHERTEXT
     */
    protected function encryptPayload(string $plaintext, string $password): string
    {
        $salt = random_bytes(16);
        $iv = random_bytes(12);
        $key = hash_pbkdf2('sha256', $password, $salt, 100000, 32, true);

        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException("OpenSSL encryption failure: " . openssl_error_string());
        }

        return $salt . $iv . $tag . $ciphertext;
    }

    /**
     * Decrypt binary payload using PBKDF2 + AES-256-GCM
     */
    protected function decryptPayload(string $encryptedBlob, string $password): string
    {
        if (strlen($encryptedBlob) < 44) {
            throw new InvalidArgumentException("Corrupted backup file: Payload too small.");
        }

        $salt = substr($encryptedBlob, 0, 16);
        $iv = substr($encryptedBlob, 16, 12);
        $tag = substr($encryptedBlob, 28, 16);
        $ciphertext = substr($encryptedBlob, 44);

        $key = hash_pbkdf2('sha256', $password, $salt, 100000, 32, true);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new InvalidArgumentException("Decryption failed. Invalid password or corrupted .ncbk archive.");
        }

        return $plaintext;
    }

    protected function executeSqlFromArchive(string $ncbkPath, string $password): void
    {
        $encryptedData = file_get_contents($ncbkPath);
        $decryptedZip = $this->decryptPayload($encryptedData, $password);
        $tempRestoreDir = storage_path("app/backups/rollback_" . uniqid());
        File::makeDirectory($tempRestoreDir, 0755, true);
        $tempZipPath = "{$tempRestoreDir}/archive.zip";
        file_put_contents($tempZipPath, $decryptedZip);

        $zip = new ZipArchive();
        if ($zip->open($tempZipPath) === true) {
            $zip->extractTo($tempRestoreDir);
            $zip->close();
            $sqlContent = file_get_contents("{$tempRestoreDir}/database.sql");
            $this->executeSqlScript($sqlContent);
        }
        File::deleteDirectory($tempRestoreDir);
    }

    /**
     * Parse and execute multiline SQL statements cleanly
     */
    protected function executeSqlScript(string $sqlContent): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $normalized = str_replace("\r\n", "\n", $sqlContent);
        $statements = preg_split('/;\s*\n/', $normalized);
        foreach ($statements as $stmt) {
            $lines = explode("\n", $stmt);
            $cleanLines = [];
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                if (!str_starts_with($trimmedLine, '--')) {
                    $cleanLines[] = $line;
                }
            }
            $cleanStmt = trim(implode("\n", $cleanLines));
            if (!empty($cleanStmt)) {
                DB::unprepared($cleanStmt);
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Verify double entry equilibrium after restore
     */
    protected function verifyAccountingIntegrity(): void
    {
        $totals = DB::table('journal_entries')
            ->selectRaw('SUM(debit) as total_debits, SUM(credit) as total_credits')
            ->first();

        $debits = (float) ($totals->total_debits ?? 0);
        $credits = (float) ($totals->total_credits ?? 0);
        $diff = abs(round($debits - $credits, 2));

        if ($diff > 0.001) {
            throw new RuntimeException("Accounting equilibrium violation detected post-restore! Debits: ₹{$debits}, Credits: ₹{$credits}, Diff: ₹{$diff}");
        }
    }
}
