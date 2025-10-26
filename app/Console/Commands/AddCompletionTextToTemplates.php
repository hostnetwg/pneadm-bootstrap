<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CertificateTemplate;

class AddCompletionTextToTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:add-completion-text';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add completion_text field to existing course_info blocks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $templates = CertificateTemplate::all();
        
        if ($templates->isEmpty()) {
            $this->info('No templates found.');
            return 0;
        }

        $this->info("Processing " . $templates->count() . " template(s)...");

        foreach ($templates as $template) {
            $config = $template->config;
            $blocks = $config['blocks'] ?? [];
            
            $updated = false;
            
            // Znajdź wszystkie bloki typu 'course_info' i dodaj im pole 'completion_text' jeśli go nie ma
            foreach ($blocks as $blockId => $block) {
                if (($block['type'] ?? '') === 'course_info') {
                    if (!isset($block['config']['completion_text'])) {
                        $this->info("Adding completion_text to template: {$template->name} (ID: {$template->id}), block: {$blockId}");
                        
                        $blocks[$blockId]['config']['completion_text'] = 'ukończył/a szkolenie';
                        $updated = true;
                        
                        $this->line("  ✓ Added: completion_text = 'ukończył/a szkolenie'");
                    }
                }
            }
            
            if ($updated) {
                $config['blocks'] = $blocks;
                $template->config = $config;
                $template->save();
                
                $this->info("  ✓ Template updated");
            } else {
                $this->line("Template {$template->name} (ID: {$template->id}) - no course_info blocks or already has completion_text");
            }
        }

        $this->info("\nDone!");
        return 0;
    }
}

