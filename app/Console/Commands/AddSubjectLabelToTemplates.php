<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CertificateTemplate;

class AddSubjectLabelToTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:add-subject-label';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add subject_label field to existing course_info blocks';

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
            
            // Znajdź wszystkie bloki typu 'course_info' i dodaj im pole 'subject_label' jeśli go nie ma
            foreach ($blocks as $blockId => $block) {
                if (($block['type'] ?? '') === 'course_info') {
                    if (!isset($block['config']['subject_label'])) {
                        $this->info("Adding subject_label to template: {$template->name} (ID: {$template->id}), block: {$blockId}");
                        
                        $blocks[$blockId]['config']['subject_label'] = 'TEMAT SZKOLENIA';
                        $updated = true;
                        
                        $this->line("  ✓ Added: subject_label = 'TEMAT SZKOLENIA'");
                    }
                }
            }
            
            if ($updated) {
                $config['blocks'] = $blocks;
                $template->config = $config;
                $template->save();
                
                $this->info("  ✓ Template updated");
            } else {
                $this->line("Template {$template->name} (ID: {$template->id}) - no course_info blocks or already has subject_label");
            }
        }

        $this->info("\nDone!");
        return 0;
    }
}

