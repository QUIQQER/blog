<?php

/**
 * This file contains \QUI\Blog\Controls\Author
 */

namespace QUI\Blog\Controls;

use QUI;
use QUI\Exception;

/**
 * Class Author
 *
 * @author  Dominik Chrzanowski
 * @package quiqqer/bricks
 */
class Author extends QUI\Control
{
    /**
     * constructor
     *
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        // default options
        $this->setAttributes([
            'class' => 'quiqqer-blog-control-author',
            'template' => 'largeImageTop' // template
        ]);

        $this->addCSSFile(dirname(__FILE__) . '/Author.css');

        parent::__construct($attributes);
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Site = $this->getSite();

        if ($Site->getAttribute("type") !== 'quiqqer/blog:blog/entry') {
            return '';
        }

        $html = match ($this->getAttribute('template')) {
            'largeImageLeft' => '/Author.LargeImageLeft.html',
            'smallImageLeft' => '/Author.SmallImageLeft.html',
            default => '/Author.LargeImageTop.html',
        };

        try {
            $authorData = $this->getAuthorData();
        } catch (Exception $Exception) {
            if (
                !$Exception instanceof QUI\Users\Exception
                || $Exception->getCode() !== 404
            ) {
                QUI\System\Log::addInfo($Exception->getMessage());
            }

            $authorData = [
                'name' => false,
                'imageUrl' => false
            ];
        }

        $Engine->assign([
            'this' => $this,
            'authorName' => $authorData['name'],
            'authorImageUrl' => $authorData['imageUrl'],
            'shortDesc' => false // todo implement user short description
        ]);

        $Engine->assign('controlTemplate', $Engine->fetch(dirname(__FILE__) . $html));

        return $Engine->fetch(dirname(__FILE__) . '/Author.html');
    }

    /**
     * Get author data for blog entry
     *
     * @return array{name: string, imageUrl: string|false}
     *
     * @throws Exception
     * @throws QUI\Users\Exception
     */
    private function getAuthorData(): array
    {
        $Site = $this->getSite();

        if ($Site->getAttribute('quiqqer.settings.blog.guestAuthor.enable')) {
            $data = $this->getGuestAuthorData();

            if ($data) {
                return $data;
            }
        }

        $User = QUI::getUsers()->get($Site->getAttribute('c_user'));
        $name = $User->getName();
        $Avatar = $User->getAvatar();
        $url = false;

        if ($Avatar) {
            $avatarUrl = $Avatar->getAttribute("url");
            $url = is_string($avatarUrl) ? $avatarUrl : false;
        }

        return [
            'name' => $name,
            'imageUrl' => $url
        ];
    }


    /**
     * Get guest author data
     *
     * @return array{name: string, imageUrl: string|false}|false
     *
     * @throws QUI\Exception
     * @throws QUI\Users\Exception
     */
    protected function getGuestAuthorData(): bool|array
    {
        $Site = $this->getSite();

        if ($Site->getAttribute('quiqqer.settings.blog.guestAuthor.quiqqerUser')) {
            $QuiqqerUser = QUI::getUsers()->get($Site->getAttribute('quiqqer.settings.blog.guestAuthor.quiqqerUser'));

            try {
                $Avatar = $QuiqqerUser->getAvatar();
                $url = false;

                if ($Avatar) {
                    $avatarUrl = $Avatar->getAttribute("url");
                    $url = is_string($avatarUrl) ? $avatarUrl : false;
                }

                return [
                    'name' => $QuiqqerUser->getName(),
                    'imageUrl' => $url
                ];
            } catch (Exception $Exception) {
                QUI\System\Log::addInfo($Exception->getMessage());
            }
        }

        $name = $Site->getAttribute('quiqqer.settings.blog.guestAuthor.name');
        $src = $Site->getAttribute('quiqqer.settings.blog.guestAuthor.avatar');

        if (!is_string($name) || $name === '') {
            return false;
        }

        return [
            'name' => $name,
            'imageUrl' => is_string($src) ? $src : false
        ];
    }

    /**
     * @return mixed|QUI\Projects\Site
     *
     * @throws QUI\Exception
     */
    protected function getSite(): mixed
    {
        if ($this->getAttribute('Site')) {
            return $this->getAttribute('Site');
        }

        $Site = QUI::getRewrite()->getSite();

        $this->setAttribute('Site', $Site);

        return $Site;
    }
}
