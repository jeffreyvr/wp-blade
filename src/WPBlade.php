<?php

namespace Jeffreyvr\WPBlade;

use Philo\Blade\Blade;

class WPBlade
{
    public $views;
    public $cache;
    public $wordpressTemplateTypes;

    public static function boot()
    {
        return new self;
    }

    public function __construct()
    {
        $this->views = get_stylesheet_directory() . '/views/';
        $this->cache = WP_CONTENT_DIR . '/blade/cache';

        $this->wordpressTemplateTypes = [
            '404',
            'archive',
            'attachment',
            'author',
            'category',
            'date',
            'embed',
            'frontpage',
            'home',
            'index',
            'page',
            'paged',
            'privacypolicy',
            'search',
            'single',
            'singular',
            'tag',
            'taxonomy'
        ];

        foreach ($this->wordpressTemplateTypes as $type) {
            \add_filter("{$type}_template_hierarchy", [$this, 'findTypeTemplates']);
        }

        \add_filter('template_include', [$this, 'render']);
        \add_filter('theme_page_templates', [$this, 'pageTemplates']);
    }

    public function pageTemplates($templates)
    {
        $files = $this->listFiles($this->views.'/templates/');

        foreach ($files as $file) {
            $template = str_replace($this->views, 'views', $file);

            $templates[$template] = ucfirst(str_replace(['views/templates/', '.blade.php', '-'], ['', '', ''], $template));
        }

        return $templates;
    }

    public function listFiles($dir)
    {
        $array = array_diff(scandir($dir), array('.', '..'));

        foreach ($array as &$item) {
            $item = $dir . $item;
        }
        unset($item);
        foreach ($array as $item) {
            if (is_dir($item)) {
                $array = array_merge($array, listAllFiles($item . DIRECTORY_SEPARATOR));
            }
        }
        return $array;
    }

    public function findTypeTemplates($templates)
    {
        foreach ($templates as $template) {
            $blade = $this->views . '/'. str_replace('.php', '', $template).'.blade.php';

            if (file_exists($blade)) {
                $bladeTemplate = "views/" . str_replace('.php', '', $template) . '.blade.php';
            }
        }

        if (!empty($bladeTemplate) && in_array('index.php', $templates)) {
            $templates = array_filter($templates, function ($template) {
                return $template != 'index.php';
            });
        }

        if (!empty($bladeTemplate)) {
            return array_merge($templates, [$bladeTemplate]);
        }

        return $templates;
    }

    public function render($template)
    {
        if (strpos($template, '.blade.php') !== false) {
            $this->view(str_replace([$this->views, '.blade.php'], ['', ''], $template));

            return;
        }

        return $template;
    }

    public function view($view)
    {
        if (!is_dir($this->cache)) {
            wp_mkdir_p($this->cache);
        }

        $blade = new Blade($this->views, $this->cache);

        $compiler = $blade->getCompiler();

        $compiler->directive('whilePosts', function ($expression) {
            return "<?php if( have_posts() ) : while(have_posts()) : the_post(); ?>";
        });

        $compiler->directive('endWhilePosts', function ($expression) {
            return "<?php endwhile; endif; ?>";
        });

        $compiler->directive('action', function ($expression) {
            return "<?php do_action($expression); ?>";
        });

        $compiler->directive('post', function ($expression) {
            return "<?php global $post; ?>";
        });

        $compiler->directive('setupPost', function ($expression) {
            return "<?php setup_postdata($expression); ?>";
        });

        echo $blade->view()->make($view)->render();
    }
}
