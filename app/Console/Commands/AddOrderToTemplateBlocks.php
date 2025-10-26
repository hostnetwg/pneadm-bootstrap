<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CertificateTemplate;

class AddOrderToTemplateBlocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:add-block-order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add order field to existing template blocks';

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
            
            $hasOrder = false;
            $updated = false;
            
            // Sprawdź czy jakikolwiek blok ma już pole 'order'
            foreach ($blocks as $block) {
                if (isset($block['order'])) {
                    $hasOrder = true;
                    break;
                }
            }
            
            // Jeśli żaden blok nie ma 'order', dodaj je
            if (!$hasOrder && !empty($blocks)) {
                $this->info("Adding order to template: {$template->name} (ID: {$template->id})");
                
                $index = 0;
                foreach ($blocks as $blockId => $block) {
                    $blocks[$blockId]['order'] = $index;
                    $index++;
                    $this->line("  Block {$blockId}: order = " . $blocks[$blockId]['order']);
                }
                
                $config['blocks'] = $blocks;
                $template->config = $config;
                $template->save();
                
                $updated = true;
                $this->info("  ✓ Updated");
            } else {
                $this->line("Template {$template->name} (ID: {$template->id}) already has order or no blocks");
            }
        }

        $this->info("\nDone!");
        return 0;
    }
}

