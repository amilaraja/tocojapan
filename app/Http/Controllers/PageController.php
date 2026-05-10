<?php

namespace App\Http\Controllers;

use App\Cms\PageTemplateRegistry;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::where('slug', $slug)->first();

        if (! $page || ! $page->isPublished()) {
            throw new NotFoundHttpException;
        }

        $template = PageTemplateRegistry::resolve($page->template_key);

        if (! $template) {
            throw new NotFoundHttpException('Unknown template: '.$page->template_key);
        }

        return $template::render($page);
    }
}
