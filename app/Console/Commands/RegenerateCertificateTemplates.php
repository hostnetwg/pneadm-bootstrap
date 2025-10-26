<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CertificateTemplate;
use App\Services\TemplateBuilderService;

class RegenerateCertificateTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:regenerate-templates {--id= : Regenerate only specific template ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate certificate template blade files from database configuration';

    protected $templateBuilder;

    public function __construct(TemplateBuilderService $templateBuilder)
    {
        parent::__construct();
        $this->templateBuilder = $templateBuilder;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('id')) {
            $templates = CertificateTemplate::where('id', $this->option('id'))->get();
            
            if ($templates->isEmpty()) {
                $this->error("Template with ID {$this->option('id')} not found.");
                return 1;
            }
        } else {
            $templates = CertificateTemplate::all();
        }

        if ($templates->isEmpty()) {
            $this->warn('No templates found to regenerate.');
            return 0;
        }

        $this->info("Regenerating " . $templates->count() . " template(s)...");

        foreach ($templates as $template) {
            try {
                $this->info("Regenerating template: {$template->name} (ID: {$template->id}, Slug: {$template->slug})");
                
                // Show current settings
                $titleSize = $template->config['settings']['title_size'] ?? 38;
                $courseTitleSize = $template->config['settings']['course_title_size'] ?? 32;
                
                $this->line("  Title size: {$titleSize}px");
                $this->line("  Course title size: {$courseTitleSize}px");
                
                // Regenerate blade file
                $fileName = $this->templateBuilder->generateBladeFile($template->config, $template->slug);
                
                $this->info("  ✓ Generated: resources/views/certificates/{$fileName}");
                
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to regenerate template {$template->name}: " . $e->getMessage());
            }
        }

        $this->info("\nDone!");
        return 0;
    }
}

