<?php
declare(strict_types=1);

final class Modules {
    // Directories are reserved for future description.json scanning.
    private string $modulesDir;
    private string $templatesDir;
    private string $storageDir;


    /**
     * Map of page => list of module renderers
     * @var array<string, array<int, string>>
     */
    private array $pageModules = [];


    public function __construct(string $modulesDir, string $templatesDir, string $storageDir) {
        $this->modulesDir = $modulesDir;
        $this->templatesDir = $templatesDir;
        $this->storageDir = $storageDir;
    }

    public function buildPageMap(): void {
        // Hardcoded minimal wiring to keep it understandable.
        // Later you can replace it with scanning description.json like in your original CMS.
        $this->pageModules = [
            'home' => ['title', 'content_home'],
            'servers' => ['title', 'content_servers'],
            'about' => ['title', 'content_about'],
            '404' => ['title', 'content_404'],
            '500' => ['title', 'content_500'],
            'module_page_admin' => ['admin'],
        ];
    }

    /** @return array<int, string> */
    public function getModulesForPage(string $route): array {
        return $this->pageModules[$route] ?? $this->pageModules['404'];
    }
}

