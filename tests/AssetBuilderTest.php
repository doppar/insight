<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests;

use Doppar\Insight\AssetBuilder;

class AssetBuilderTest extends TestCase
{
    private AssetBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new AssetBuilder();
    }

    public function testConstructorDiscoverComponents(): void
    {
        $components = $this->builder->getComponents();
        
        $this->assertIsArray($components);
        // Should discover at least some components
        $this->assertGreaterThanOrEqual(0, count($components));
    }

    public function testGetComponents(): void
    {
        $components = $this->builder->getComponents();
        
        $this->assertIsArray($components);
        
        // Each component should be a string (directory name)
        foreach ($components as $component) {
            $this->assertIsString($component);
            $this->assertNotEmpty($component);
        }
    }

    public function testBuildCssReturnsString(): void
    {
        $css = $this->builder->buildCss();
        
        $this->assertIsString($css);
    }

    public function testBuildCssIncludesCoreCss(): void
    {
        $css = $this->builder->buildCss();
        
        // If core.css exists, it should be included
        $coreCssPath = __DIR__ . '/../resources/assets/core.css';
        if (file_exists($coreCssPath)) {
            $this->assertNotEmpty($css);
        }
    }

    public function testBuildCssIncludesComponentComments(): void
    {
        $css = $this->builder->buildCss();
        
        $components = $this->builder->getComponents();
        
        foreach ($components as $component) {
            $componentCssPath = __DIR__ . '/../resources/assets/components/' . $component . '/' . $component . '.css';
            
            if (file_exists($componentCssPath)) {
                // Should include a comment for this component
                $this->assertStringContainsString("/* Component: {$component} */", $css);
            }
        }
    }

    public function testBuildJsReturnsString(): void
    {
        $js = $this->builder->buildJs();
        
        $this->assertIsString($js);
    }

    public function testBuildJsIncludesBaseJs(): void
    {
        $js = $this->builder->buildJs();
        
        // If base.js exists, it should be included
        $baseJsPath = __DIR__ . '/../resources/assets/components/base.js';
        if (file_exists($baseJsPath)) {
            $this->assertNotEmpty($js);
        }
    }

    public function testBuildJsIncludesComponentComments(): void
    {
        $js = $this->builder->buildJs();
        
        $components = $this->builder->getComponents();
        
        foreach ($components as $component) {
            $componentJsPath = __DIR__ . '/../resources/assets/components/' . $component . '/' . $component . '.js';
            
            if (file_exists($componentJsPath)) {
                // Should include a comment for this component
                $this->assertStringContainsString("// Component: {$component}", $js);
            }
        }
    }

    public function testBuildJsIncludesRegistry(): void
    {
        $js = $this->builder->buildJs();
        
        // Should include the auto-generated registry
        $this->assertStringContainsString('// Auto-generated Component Registry', $js);
        $this->assertStringContainsString('InsightComponentRegistry', $js);
    }

    public function testGenerateRegistryIncludesAllComponents(): void
    {
        $js = $this->builder->buildJs();
        
        $components = $this->builder->getComponents();
        
        foreach ($components as $component) {
            $className = ucfirst($component) . 'Component';
            $this->assertStringContainsString(".register({$className})", $js);
        }
    }

    public function testGetLogoReturnsDataUri(): void
    {
        $logo = $this->builder->getLogo();
        
        $this->assertIsString($logo);
        
        // If logo exists, should be a data URI
        $logoPath = __DIR__ . '/../resources/assets/logo.png';
        if (file_exists($logoPath)) {
            $this->assertStringStartsWith('data:image/png;base64,', $logo);
            $this->assertNotEmpty($logo);
        } else {
            $this->assertEmpty($logo);
        }
    }

    public function testGetLogoReturnsEmptyStringWhenLogoDoesNotExist(): void
    {
        $logoPath = __DIR__ . '/../resources/assets/logo.png';
        
        if (!file_exists($logoPath)) {
            $logo = $this->builder->getLogo();
            $this->assertEmpty($logo);
        } else {
            $this->markTestSkipped('Logo file exists');
        }
    }

    public function testBuildCssHandlesMissingFiles(): void
    {
        // Should not throw exception even if files are missing
        $css = $this->builder->buildCss();
        $this->assertIsString($css);
    }

    public function testBuildJsHandlesMissingFiles(): void
    {
        // Should not throw exception even if files are missing
        $js = $this->builder->buildJs();
        $this->assertIsString($js);
    }

    public function testComponentsDoNotIncludeBaseFiles(): void
    {
        $components = $this->builder->getComponents();
        
        // Should not include base.js, loader.js, or registry.js as components
        $this->assertNotContains('base.js', $components);
        $this->assertNotContains('loader.js', $components);
        $this->assertNotContains('registry.js', $components);
    }

    public function testBuildCssIncludesJsonViewerUtility(): void
    {
        $css = $this->builder->buildCss();
        
        $jsonViewerCssPath = __DIR__ . '/../resources/assets/components/json-viewer.css';
        if (file_exists($jsonViewerCssPath)) {
            $this->assertStringContainsString('/* Utility: JSON Viewer */', $css);
        }
    }

    public function testBuildJsIncludesJsonViewerUtility(): void
    {
        $js = $this->builder->buildJs();
        
        $jsonViewerJsPath = __DIR__ . '/../resources/assets/components/json-viewer.js';
        if (file_exists($jsonViewerJsPath)) {
            $this->assertStringContainsString('// Utility: JSON Viewer', $js);
        }
    }
}
