<?php

namespace QUI\Blog\Controls;

use QUI;
use QUI\Interfaces\Projects\Site;
use QUI\Blog\Utils\AuthorData;
use QUI\Projects\Colors;

class Author extends QUI\Control
{
    private const AVATAR_SIZES = [
        'left' => 96,
        'topLeft' => 112,
        'top' => 112,
        'topTextLeft' => 112,
        'compact' => 48
    ];

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes([
            'nodeName' => 'aside',
            'class' => 'quiqqer-blog-controls-author',
            'authorLayout' => 'left',
            'initialsBackground' => '',
            'initialsColor' => ''
        ]);

        parent::__construct($attributes);
        // Brick runtime defaults must never enable a page-independent HTML cache.
        $this->setAttribute('cacheable', false);
        $this->addCSSFile(__DIR__ . '/Author.css');
    }

    public function create(): string
    {
        if (AuthorData::resolve($this->getSite()) === null) {
            return '';
        }

        return parent::create();
    }

    public function getBody(): string
    {
        $author = AuthorData::resolve($this->getSite());

        if ($author === null) {
            return '';
        }

        $layout = $this->getAttribute('authorLayout');

        if (!is_string($layout) || !isset(self::AVATAR_SIZES[$layout])) {
            $layout = 'left';
        }

        foreach (['initialsBackground', 'initialsColor'] as $setting) {
            $value = $this->getAttribute($setting);

            // MCP and saved brick settings can supply decoded color objects.
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            unset($this->styles['--_q-controlConf-' . $setting]);
            $this->setCustomVariable($setting, Colors::toCss($value));
        }

        $Engine = QUI::getTemplateManager()->getEngine();
        $Engine->assign([
            'this' => $this,
            'authorProfile' => $author,
            'authorLayout' => $layout,
            'authorAvatar' => AuthorData::getAvatarUrl($author['avatar'], self::AVATAR_SIZES[$layout] * 2)
        ]);

        return $Engine->fetch(__DIR__ . '/Author.html');
    }

    protected function getSite(): Site
    {
        $Site = $this->getAttribute('Site');

        if ($Site instanceof Site) {
            return $Site;
        }

        $Site = QUI::getRewrite()->getSite();

        if (!$Site instanceof Site) {
            throw new QUI\Exception('No site available for the blog author.');
        }

        return $Site;
    }

    private function setCustomVariable(string $name, string $value): void
    {
        if ($value !== '') {
            $this->setStyle('--_q-controlConf-' . $name, $value);
        }
    }
}
