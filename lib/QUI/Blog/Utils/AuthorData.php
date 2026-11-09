<?php

namespace QUI\Blog\Utils;

use Normalizer;
use QUI;
use QUI\Interfaces\Projects\Site;
use QUI\Projects\Media\Utils as MediaUtils;

/** Page-owned author profile data, independent of the card layout. */
class AuthorData
{
    /**
     * @return array{name:string, avatar:mixed, initials:string, description:string, additionalInfo:string}|null
     */
    public static function resolve(Site $Site): ?array
    {
        if ($Site->getAttribute('type') !== 'quiqqer/blog:blog/entry') {
            return null;
        }

        $prefix = 'quiqqer.settings.blog.guestAuthor.';
        $userId = $Site->getAttribute('c_user');
        $name = '';
        $avatar = null;
        $description = '';
        $additionalInfo = '';

        if ($Site->getAttribute($prefix . 'enable')) {
            $guestUser = $Site->getAttribute($prefix . 'quiqqerUser');
            $guestName = self::normalizeName((string)$Site->getAttribute($prefix . 'name'));

            if ($guestUser) {
                $userId = $guestUser;
            } elseif ($guestName !== '') {
                $userId = null;
                $name = $guestName;
                $avatar = $Site->getAttribute($prefix . 'avatar');
                $description = trim((string)$Site->getAttribute($prefix . 'description'));
                $additionalInfo = self::normalizeName((string)$Site->getAttribute($prefix . 'additionalInfo'));
            }
        }

        if ($userId) {
            try {
                $User = QUI::getUsers()->get($userId);
                $name = self::normalizeName($User->getName());
                // getAvatar() may return a generic project placeholder.
                $avatar = $User->getAttribute('avatar');
            } catch (QUI\Exception $Exception) {
                if (
                    !$Exception instanceof QUI\Users\Exception
                    || $Exception->getCode() !== 404
                ) {
                    $Project = $Site->getProject();
                    QUI\System\Log::addInfo($Exception->getMessage(), [
                        'project' => $Project->getName(),
                        'lang' => $Project->getLang(),
                        'site' => $Site->getId()
                    ]);
                }

                return null;
            }
        }

        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'avatar' => $avatar,
            'initials' => self::getInitials($name),
            'description' => $description,
            'additionalInfo' => $additionalInfo
        ];
    }

    public static function normalizeName(string $name): string
    {
        $name = preg_replace('/[\p{Z}\s]+/u', ' ', $name);
        return trim($name ?? '');
    }

    public static function getInitials(string $name): string
    {
        $name = self::normalizeName($name);

        if ($name === '') {
            return '';
        }

        $parts = explode(' ', $name);
        preg_match('/^\X/u', $parts[0], $first);
        $initials = $first[0] ?? '';

        if (count($parts) > 1) {
            preg_match('/^\X/u', $parts[count($parts) - 1], $last);
            $initials .= $last[0] ?? '';
        }

        $initials = mb_strtoupper($initials, 'UTF-8');
        return Normalizer::normalize($initials, Normalizer::FORM_C) ?: $initials;
    }

    public static function getAvatarUrl(mixed $avatar, int $size): string
    {
        if (!MediaUtils::isMediaUrl($avatar)) {
            return '';
        }

        try {
            $Image = MediaUtils::getImageByUrl($avatar);

            if (!$Image->isActive() || !is_file($Image->getFullPath())) {
                return '';
            }

            return $Image->getSizeCacheUrl($size, $size);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addInfo($Exception->getMessage());
            return '';
        }
    }
}
