<?php

namespace App\Modules\Crm\Filament\Pages;

use App\Modules\Crm\Support\CrmAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/** CRM → CRM Help: the operating guide (app/Modules/Crm/docs/help.md). */
class CrmHelp extends Page
{
    protected string $view = 'crm::filament.help';

    protected static ?string $slug = 'crm/help';

    protected static ?string $title = 'CRM Help';

    protected static ?string $navigationLabel = 'CRM Help';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 99;

    public static function canAccess(): bool
    {
        return CrmAccess::canUse();
    }

    /** @return array{html: string, toc: list<array{id: string, title: string}>} */
    public function guide(): array
    {
        $markdown = (string) file_get_contents(dirname(__DIR__, 2).'/docs/help.md');
        // The page title already says what this is.
        $markdown = preg_replace('/^# .+\n/', '', $markdown, 1);

        $html = (string) (new GithubFlavoredMarkdownConverter(['html_input' => 'escape', 'allow_unsafe_links' => false]))->convert($markdown);

        $toc = [];
        $html = preg_replace_callback('/<h2>(.*?)<\/h2>/', function ($m) use (&$toc) {
            $title = html_entity_decode(strip_tags($m[1]));
            $id = Str::slug($title);
            $toc[] = ['id' => $id, 'title' => $title];

            return '<h2 id="'.$id.'">'.$m[1].'</h2>';
        }, $html);

        return ['html' => $html, 'toc' => $toc];
    }
}
