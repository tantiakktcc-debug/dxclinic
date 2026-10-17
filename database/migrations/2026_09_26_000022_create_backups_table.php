<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('file_path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status', 30)->default('COMPLETED'); // COMPLETED, FAILED, VERIFIED
            $table->string('checksum', 64); // SHA-256
            $table->string('schema_version', 20)->default('1.0');
            $table->string('backup_type', 30)->default('MANUAL'); // MANUAL, AUTOMATIC, PRE_RESTORE
            $table->string('storage_location', 50)->default('local');
            $table->text('notes')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
