<?php

namespace QUITests\Blog;

use PHPUnit\Framework\TestCase;
use QUI\Projects\Project;
use QUI\Projects\Site;

class BlogListTest extends TestCase
{
    public function testExistingBlogUsesDirectChildrenAndLeavesFiltersDisabled(): void
    {
        $Site = $this->createMock(Site::class);
        $Site->method('getProject')->willReturn($this->createMock(Project::class));
        $Site->method('getAttribute')->willReturnMap([
            ['quiqqer.settings.blog.sitesToDisplay', 'children'],
            ['quiqqer.settings.blog.template', 'cards']
        ]);

        $List = require dirname(__DIR__, 3) . '/blog/list.children.php';

        self::assertFalse($List->isFilterEnabled());
        self::assertFalse($List->getAttribute('byType'));
        self::assertSame(['type' => 'quiqqer/blog:blog/entry'], $List->getAttribute('where'));
    }

    public function testDefinitionBuildsProjectWideFilteredBlog(): void
    {
        $Project = $this->createMock(Project::class);
        $Site = $this->createMock(Site::class);
        $Site->method('getProject')->willReturn($Project);
        $Site->method('getAttribute')->willReturnMap([
            ['quiqqer.settings.blog.sitesToDisplay', 'all'],
            ['quiqqer.settings.blog.filter', 'all'],
            ['quiqqer.settings.blog.tags', 'Projekte,Technik'],
            ['quiqqer.settings.blog.max', 2],
            ['quiqqer.settings.blog.template', 'cards']
        ]);
        $List = require dirname(__DIR__, 3) . '/blog/list.children.php';

        self::assertTrue($List->isFilterEnabled());
        self::assertSame($Site, $List->getAttribute('Site'));
        self::assertSame($Project, $List->getAttribute('Project'));
        self::assertTrue($List->getAttribute('isMainList'));
        self::assertSame('Projekte,Technik', $List->getAttribute('tags'));
        self::assertSame('quiqqer/blog:blog/entry', $List->getAttribute('byType'));
        self::assertSame(2, $List->getAttribute('limit'));
        self::assertSame('quiqqer.settings.blog.pinned', $List->getAttribute('pinnedAttribute'));
        self::assertSame('release_from DESC', $List->getAttribute('pinnedOrder'));
    }
}
