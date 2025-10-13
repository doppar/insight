<?php

namespace Doppar\Insight;

class AssetBuilder
{
    protected string $assetsPath;
    protected array $components = [];
    
    public function __construct()
    {
        $this->assetsPath = __DIR__ . '/../resources/assets';
        $this->discoverComponents();
    }
    
    /**
     * Automatically discover all components in the components directory
     */
    protected function discoverComponents(): void
    {
        $componentsPath = $this->assetsPath . '/components';
        
        if (!is_dir($componentsPath)) {
            return;
        }
        
        $dirs = scandir($componentsPath);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..' || !is_dir($componentsPath . '/' . $dir)) {
                continue;
            }
            
            // Skip base files
            if (in_array($dir, ['base.js', 'loader.js', 'registry.js'])) {
                continue;
            }
            
            $this->components[] = $dir;
        }
    }
    
    /**
     * Build combined CSS from core + utilities + all components
     */
    public function buildCss(): string
    {
        $css = '';
        
        // Core CSS
        $coreCss = $this->assetsPath . '/core.css';
        if (is_file($coreCss)) {
            $css .= file_get_contents($coreCss) . "\n\n";
        }
        
        // JSON Viewer utility CSS
        $jsonViewerCss = $this->assetsPath . '/components/json-viewer.css';
        if (is_file($jsonViewerCss)) {
            $css .= "/* Utility: JSON Viewer */\n";
            $css .= file_get_contents($jsonViewerCss) . "\n\n";
        }
        
        // Component CSS
        foreach ($this->components as $component) {
            $componentCss = $this->assetsPath . '/components/' . $component . '/' . $component . '.css';
            if (is_file($componentCss)) {
                $css .= "/* Component: {$component} */\n";
                $css .= file_get_contents($componentCss) . "\n\n";
            }
        }
        
        return $css;
    }
    
    /**
     * Build combined JS from base + loader + utilities + all components + registry
     */
    public function buildJs(): string
    {
        $js = '';
        
        // Base class
        $baseJs = $this->assetsPath . '/components/base.js';
        if (is_file($baseJs)) {
            $js .= file_get_contents($baseJs) . "\n\n";
        }
        
        // JSON Viewer utility
        $jsonViewerJs = $this->assetsPath . '/components/json-viewer.js';
        if (is_file($jsonViewerJs)) {
            $js .= "// Utility: JSON Viewer\n";
            $js .= file_get_contents($jsonViewerJs) . "\n\n";
        }
        
        // Loader
        $loaderJs = $this->assetsPath . '/components/loader.js';
        if (is_file($loaderJs)) {
            $js .= file_get_contents($loaderJs) . "\n\n";
        }
        
        // Component JS
        foreach ($this->components as $component) {
            $componentJs = $this->assetsPath . '/components/' . $component . '/' . $component . '.js';
            if (is_file($componentJs)) {
                $js .= "// Component: {$component}\n";
                $js .= file_get_contents($componentJs) . "\n\n";
            }
        }
        
        // Auto-generate registry
        $js .= $this->generateRegistry();
        
        return $js;
    }
    
    /**
     * Auto-generate component registry based on discovered components
     */
    protected function generateRegistry(): string
    {
        $registry = "// Auto-generated Component Registry\n";
        $registry .= "InsightComponentRegistry\n";
        
        foreach ($this->components as $component) {
            $className = ucfirst($component) . 'Component';
            $registry .= "    .register({$className})\n";
        }
        
        $registry .= ";\n";
        
        return $registry;
    }
    
    /**
     * Get list of discovered components
     */
    public function getComponents(): array
    {
        return $this->components;
    }
}
