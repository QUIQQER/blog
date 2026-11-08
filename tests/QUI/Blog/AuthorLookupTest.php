<?php

namespace QUITests\Blog;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Interfaces\Template\EngineInterface;
use QUI\Interfaces\Users\User;
use QUI\Projects\Project;
use QUI\Projects\Site;
use QUI\Template;
use QUI\Users\Manager;
use QUI\Utils\JsonLd;

class AuthorLookupTest extends TestCase
{
    private ?Manager $previousUsers;
    private ?Template $previousTemplate;
    private array $previousConfigs;
    private Logger $previousLogger;
    private TestHandler $Handler;
    private array $assigned = [];

    protected function setUp(): void
    {
        $this->previousUsers = QUI::$Users;
        $this->previousTemplate = QUI::$Template;
        $this->previousConfigs = QUI::$Configs;
        $this->previousLogger = QUI\Log\Logger::getLogger();
        $this->Handler = new TestHandler();
        QUI\Log\Logger::$Logger = new Logger('author-test', [$this->Handler]);
        $Config = $this->createMock(QUI\Config::class);
        $Config->method('get')->willReturnCallback(static fn ($section) => $section === 'log_levels');
        QUI::$Configs['etc/plugins/quiqqer/log.ini.php'] = $Config;
    }

    protected function tearDown(): void
    {
        QUI::$Users = $this->previousUsers;
        QUI::$Template = $this->previousTemplate;
        QUI::$Configs = $this->previousConfigs;
        QUI\Log\Logger::$Logger = $this->previousLogger;
    }

    public static function authorCases(): iterable
    {
        foreach (['list', 'entry', 'author'] as $type) {
            foreach (['present', 'missing', 'user-error', 'other-404'] as $result) {
                yield "$type: $result" => [$type, $result, false];
            }

            if ($type !== 'list') {
                yield "$type: selected guest" => [$type, 'present', true];
                yield "$type: deleted guest" => [$type, 'missing', true];
                yield "$type: guest name" => [$type, 'guest-name', true];
            }
        }
    }

    #[DataProvider('authorCases')]
    public function testAuthorLookupPreservesOutputAndOnlySuppressesMissingUsers(
        string $type,
        string $result,
        bool $guest
    ): void {
        $message = 'Author lookup regression';
        $Users = $this->createMock(Manager::class);
        QUI::$Users = $Users;
        $User = $this->createMock(User::class);
        $User->method('getName')->willReturn('Existing author');
        $User->method('getAttribute')->willReturn('Existing');

        if ($result === 'guest-name') {
            $Users->expects($this->never())->method('get');
        } else {
            $lookup = $Users->expects($this->atLeastOnce())->method('get')->with($guest ? 'guest-uuid' : 'creator-uuid');

            if ($result === 'present') {
                $lookup->willReturn($User);
            } else {
                $Exception = match ($result) {
                    'missing' => new QUI\Users\Exception($message, 404),
                    'user-error' => new QUI\Users\Exception($message, 500),
                    default => new QUI\Exception($message, 404)
                };
                $lookup->willThrowException($Exception);
            }
        }

        $Project = $this->createMock(Project::class);
        $Project->method('getName')->willReturn('author-test');
        $Project->method('getLang')->willReturn('de');
        $Project->method('getConfig')->willReturnCallback(static fn ($key) => match ($key) {
            'publisher' => 'Test publisher',
            'publisher_url' => 'https://example.org/',
            'news.settings.entry.more.amount', 'blog.settings.entries.more.amount' => 0,
            default => ''
        });
        $attributes = [
            'type' => 'quiqqer/blog:blog/entry',
            'title' => 'Test entry',
            'short' => 'Summary',
            'c_user' => 'creator-uuid',
            'c_date' => '2026-11-05 12:00:00',
            'release_from' => '2026-11-05 12:00:00',
            'image_site' => 'https://example.org/image.jpg',
            'quiqqer.tags.tagList' => [],
            'quiqqer.settings.blog.entry.dateAndCreator' => 'showAll',
            'quiqqer.news.settings.meta.visibility' => 'showAll',
            'quiqqer.settings.blog.guestAuthor.enable' => $guest,
            'quiqqer.settings.blog.guestAuthor.quiqqerUser' => $result === 'guest-name' ? '' : 'guest-uuid',
            'quiqqer.settings.blog.guestAuthor.name' => 'Guest writer',
            'quiqqer.settings.blog.max' => 1,
            'quiqqer.settings.blog.showCreator' => false,
            'quiqqer.settings.blog.filter' => 'disabled',
            'quiqqer.settings.blog.tags' => []
        ];
        $Site = $this->createMock(Site::class);
        $Site->method('load')->willReturnSelf();
        $Site->method('getId')->willReturn(42);
        $Site->method('getAttribute')->willReturnCallback(static fn ($key) => $attributes[$key] ?? '');
        $Site->method('getProject')->willReturn($Project);
        $Site->method('getUrlRewrittenWithHost')->willReturn('https://example.org/entry');
        $Site->method('getUrlRewritten')->willReturn('/entry');
        $Site->method('previousSiblings')->willReturn([]);
        $Site->method('nextSiblings')->willReturn([]);
        $Site->method('getChildren')->willReturnCallback(
            static fn ($params) => isset($params['count']) ? 1 : [$Site]
        );
        $Engine = $this->createMock(EngineInterface::class);
        $Engine->method('fetch')->willReturn('');
        $Engine->method('assign')->willReturnCallback(function ($key, $value = false): void {
            $values = is_array($key) ? $key : [$key => $value];
            $this->assigned = array_replace($this->assigned, $values);
        });
        $Template = $this->createMock(Template::class);
        $Page = new JsonLd();
        $Page->set('type', 'WebPage');
        $Template->method('getEngine')->willReturn($Engine);
        $Template->method('getJsonLd')->willReturn($Page);
        QUI::$Template = $Template;

        if ($type === 'author') {
            $Control = new QUI\Blog\Controls\Author(['Site' => $Site]);
            $Control->getBody();
        } else {
            require dirname(__DIR__, 3) . '/blog/' . $type . '.php';
        }

        if ($type === 'list') {
            $article = $Page->getJsonLdNode('blog')['blogPost'][0];
        } elseif ($type === 'author') {
            $article = ['author' => $this->assigned['authorProfile']['name'] ?? null];
        } else {
            $article = $Page->getJsonLdNode('blogPosting');
        }

        $this->assertNotNull($article);

        if ($type === 'entry') {
            $this->assertSame($article['author']['name'] ?? null, $this->assigned['author']);
        }

        if ($result === 'present' || $result === 'guest-name') {
            $expected = $result === 'present' ? 'Existing author' : 'Guest writer';
            $actual = $type === 'author' ? $article['author'] : $article['author']['name'];
            $this->assertSame($expected, $actual);
        } elseif ($type === 'author') {
            $this->assertNull($article['author']);
        } else {
            $this->assertArrayNotHasKey('author', $article);
        }

        $records = array_filter(
            $this->Handler->getRecords(),
            static fn ($record) => $record['message'] === $message
        );
        $shouldLog = $result === 'user-error' || $result === 'other-404';
        $this->assertSame($shouldLog, $records !== [], 'Only a missing user may be silently omitted.');
    }
}
