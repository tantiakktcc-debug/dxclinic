<?php

namespace App\Livewire;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;

class HelpCenter extends Component
{
    public string $activeTab = 'operator-quick';
    public string $search = '';
    public array $currentMeta = [];

    protected array $catalog = [
        'operator-quick' => [
            'title' => 'Operator Quick Card',
            'full_title' => 'OPERATOR — QUICK OPERATION CARD (1-PAGE)',
            'type' => '1-Page Quick Reference Card',
            'icon' => 'bolt',
            'role' => 'Operator / Front Desk',
            'file' => 'knowledge/manuals/operator_quick_card.md',
            'category' => 'Quick Cards',
        ],
        'operator' => [
            'title' => 'Full Operator Manual',
            'full_title' => 'Operator Complete Desk Operation Manual',
            'type' => 'Standard Operating Procedure (SOP)',
            'icon' => 'clipboard-document-list',
            'role' => 'Operator / Receptionist',
            'file' => 'knowledge/manuals/operator_manual.md',
            'category' => 'Manuals',
        ],
        'doctor-quick' => [
            'title' => 'Doctor Quick Card',
            'full_title' => 'DOCTOR — QUICK OPERATION CARD (1-PAGE)',
            'type' => '1-Page Quick Reference Card',
            'icon' => 'heart',
            'role' => 'Attending Physician',
            'file' => 'knowledge/manuals/doctor_quick_card.md',
            'category' => 'Quick Cards',
        ],
        'doctor' => [
            'title' => 'Doctor & Pad Manual',
            'full_title' => 'Attending Physician & Doctor Operations Manual',
            'type' => 'Full Doctor Operation Manual',
            'icon' => 'user',
            'role' => 'Attending Physician',
            'file' => 'knowledge/manuals/doctor_manual.md',
            'category' => 'Manuals',
        ],
        'admin-quick' => [
            'title' => 'Admin Quick Card',
            'full_title' => 'ADMIN — QUICK ADMINISTRATION CARD (1-PAGE)',
            'type' => '1-Page Quick Reference Card',
            'icon' => 'cog',
            'role' => 'Clinic Administrator',
            'file' => 'knowledge/manuals/admin_quick_card.md',
            'category' => 'Quick Cards',
        ],
        'admin' => [
            'title' => 'Full Admin Manual',
            'full_title' => 'Administrator Complete Setup & Controls Manual',
            'type' => 'Full Admin Manual',
            'icon' => 'adjustments-horizontal',
            'role' => 'Clinic Administrator',
            'file' => 'knowledge/manuals/admin_manual.md',
            'category' => 'Manuals',
        ],
        'whatsapp' => [
            'title' => 'WhatsApp Desk & AI Booking',
            'full_title' => 'WhatsApp Operator Desk & AI Booking Manual',
            'type' => 'Standard Operating Procedure & Integration Architecture',
            'icon' => 'chat-bubble-left-right',
            'role' => 'Operator / Admin',
            'file' => 'knowledge/manuals/whatsapp_manual.md',
            'category' => 'Integrations',
        ],
        'financial-workflows' => [
            'title' => 'Financial Workflows',
            'full_title' => 'Financial Workflows & Double-Entry Reconciliation Guide',
            'type' => 'Accounting Architecture Manual',
            'icon' => 'banknotes',
            'role' => 'Admin / Auditor',
            'file' => 'knowledge/workflows/financial_workflows.md',
            'category' => 'Accounting',
        ],
        'faq' => [
            'title' => 'Searchable FAQ',
            'full_title' => 'Clinical, Operational & Financial FAQ',
            'type' => 'Searchable Knowledge Base',
            'icon' => 'question-mark-circle',
            'role' => 'All Roles',
            'file' => 'knowledge/faq/faq.md',
            'category' => 'Knowledge Base',
        ],
        'troubleshooting' => [
            'title' => 'Troubleshooting',
            'full_title' => 'System Troubleshooting & Diagnostics Manual',
            'type' => 'System Diagnostics Guide',
            'icon' => 'wrench-screwdriver',
            'role' => 'Administrator / Support',
            'file' => 'knowledge/troubleshooting/troubleshooting.md',
            'category' => 'Support',
        ],
    ];

    public function mount(?string $tab = null): void
    {
        $user = auth()->user();
        if (!empty($tab) && isset($this->catalog[$tab])) {
            $this->activeTab = $tab;
        } elseif ($user && $user->isAdmin()) {
            $this->activeTab = 'admin-quick';
        } elseif ($user && $user->isDoctor()) {
            $this->activeTab = 'doctor-quick';
        } else {
            $this->activeTab = 'operator-quick';
        }
        $this->currentMeta = $this->catalog[$this->activeTab] ?? $this->catalog['operator-quick'];
    }

    public function setTab(string $tab): void
    {
        if (isset($this->catalog[$tab])) {
            $this->activeTab = $tab;
            $this->currentMeta = $this->catalog[$tab];
            $this->search = '';
        }
    }

    protected function loadContent(string $relativePath): string
    {
        $path = base_path($relativePath);
        if (File::exists($path)) {
            return File::get($path);
        }
        return "# Documentation File Not Found\n\nThe requested file `{$relativePath}` could not be located on the server.";
    }

    protected function extractTableOfContents(string $markdown): array
    {
        $toc = [];
        $lines = explode("\n", $markdown);

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^(#{2,3})\s+(.+)$/', $trimmed, $matches)) {
                $level = strlen($matches[1]); // 2 or 3
                $title = trim($matches[2]);
                // Remove markdown formatting like bold/links for clean title
                $cleanTitle = strip_tags(Str::markdown($title));
                $slug = Str::slug($cleanTitle);
                $toc[] = [
                    'level' => $level,
                    'title' => trim($cleanTitle),
                    'slug' => $slug,
                ];
            }
        }

        return $toc;
    }

    protected function enrichHtmlContent(string $html): string
    {
        // 1. Enrich callouts for notes, warnings, tips
        $html = preg_replace(
            '/<blockquote>\s*<p>\s*(\[!NOTE\]|\*\*NOTE:?\*\*|\*\*IMPORTANT:?\*\*)\s*(.*?)<\/p>\s*<\/blockquote>/is',
            '<div class="my-4 p-4 rounded-xl border-l-4 border-blue-600 bg-blue-50/70 text-slate-800 text-xs shadow-xs"><div class="flex items-center gap-1.5 font-bold text-blue-900 mb-1"><svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>NOTE / IMPORTANT</div><p class="text-xs text-slate-700 leading-relaxed">$2</p></div>',
            $html
        );

        $html = preg_replace(
            '/<blockquote>\s*<p>\s*(\[!WARNING\]|\*\*WARNING:?\*\*|\*\*CAUTION:?\*\*)\s*(.*?)<\/p>\s*<\/blockquote>/is',
            '<div class="my-4 p-4 rounded-xl border-l-4 border-amber-600 bg-amber-50/70 text-slate-800 text-xs shadow-xs"><div class="flex items-center gap-1.5 font-bold text-amber-900 mb-1"><svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>CRITICAL WARNING</div><p class="text-xs text-slate-700 leading-relaxed">$2</p></div>',
            $html
        );

        $html = preg_replace(
            '/<blockquote>\s*<p>\s*(\[!TIP\]|\*\*TIP:?\*\*)\s*(.*?)<\/p>\s*<\/blockquote>/is',
            '<div class="my-4 p-4 rounded-xl border-l-4 border-emerald-600 bg-emerald-50/70 text-slate-800 text-xs shadow-xs"><div class="flex items-center gap-1.5 font-bold text-emerald-900 mb-1"><svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>OPERATIONAL TIP</div><p class="text-xs text-slate-700 leading-relaxed">$2</p></div>',
            $html
        );

        // 2. Add IDs to h2 and h3 headers for Table of Contents anchoring
        $html = preg_replace_callback('/<(h[23])>(.*?)<\/\1>/i', function ($matches) {
            $tag = $matches[1];
            $inner = $matches[2];
            $cleanTitle = strip_tags($inner);
            $slug = Str::slug($cleanTitle);
            return "<{$tag} id=\"{$slug}\" class=\"scroll-mt-20 group relative\">{$inner} <a href=\"#{$slug}\" class=\"opacity-0 group-hover:opacity-100 text-teal-600 ml-1.5 text-xs font-normal\" title=\"Direct link to this section\">#</a></{$tag}>";
        }, $html);

        // 3. Style tables to match PDF doc-table
        $html = str_replace('<table>', '<div class="overflow-x-auto my-5 rounded-xl border border-slate-200 shadow-xs"><table class="doc-table w-full text-xs text-left text-slate-700 divide-y divide-slate-200">', $html);
        $html = str_replace('</table>', '</table></div>', $html);
        $html = str_replace('<thead>', '<thead class="bg-slate-100 font-bold text-slate-900 uppercase tracking-wider text-[11px]">', $html);
        $html = str_replace('<th>', '<th class="px-4 py-2.5 border-r border-slate-200 last:border-r-0">', $html);
        $html = str_replace('<td>', '<td class="px-4 py-2 border-b border-r border-slate-100 last:border-r-0">', $html);

        return $html;
    }

    public function render()
    {
        $currentMeta = $this->catalog[$this->activeTab] ?? $this->catalog['operator-quick'];
        $rawMarkdown = $this->loadContent($currentMeta['file']);

        // Filter if search is active
        if (!empty($this->search)) {
            $lines = explode("\n", $rawMarkdown);
            $matchedBlocks = [];
            $buffer = [];
            $found = false;

            foreach ($lines as $line) {
                if (str_starts_with($line, '#')) {
                    if ($found && !empty($buffer)) {
                        $matchedBlocks[] = implode("\n", $buffer);
                    }
                    $buffer = [$line];
                    $found = (stripos($line, $this->search) !== false);
                } else {
                    $buffer[] = $line;
                    if (stripos($line, $this->search) !== false) {
                        $found = true;
                    }
                }
            }
            if ($found && !empty($buffer)) {
                $matchedBlocks[] = implode("\n", $buffer);
            }

            $contentToRender = !empty($matchedBlocks)
                ? implode("\n\n---\n\n", $matchedBlocks)
                : "# Search Results\n\nNo sections found matching **" . htmlspecialchars($this->search) . "** in this document.\n\nTry clearing your search query or selecting a different manual from the catalog tabs above.";
        } else {
            $contentToRender = $rawMarkdown;
        }

        $toc = $this->extractTableOfContents($rawMarkdown);
        $baseHtml = Str::markdown($contentToRender);
        $renderedHtml = $this->enrichHtmlContent($baseHtml);

        return view('livewire.help-center', [
            'catalog' => $this->catalog,
            'currentMeta' => $currentMeta,
            'renderedHtml' => $renderedHtml,
            'toc' => $toc,
        ])->layout('components.layouts.app', ['title' => 'Help Center & Documentation']);
    }
}
