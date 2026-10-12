<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Validation;

use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;

/** Checks shared by the formats: required texts and links, lengths, price, identifiers. */
final class ItemValidator
{
    /**
     * @param array{id: int, title: int, description: int} $maxLengths
     * @param list<string> $required neutral fields required by the format among
     *                               title, description, link, image, brand
     *
     * @return list<Issue>
     */
    public static function validate(FeedItem $item, array $maxLengths, array $required): array
    {
        $issues = [];
        if (mb_strlen($item->id) > $maxLengths['id']) {
            $issues[] = Issue::error(Issue::TOO_LONG, 'id', ['%max%' => $maxLengths['id'], '%length%' => mb_strlen($item->id)]);
        }
        $present = [
            'title' => '' !== $item->title,
            'description' => '' !== $item->description,
            'link' => '' !== $item->link,
            'image' => null !== $item->imageLink,
            'brand' => null !== $item->brand,
        ];
        foreach ($required as $field) {
            if (!($present[$field] ?? true)) {
                $issues[] = Issue::error(Issue::MISSING_FIELD, $field);
            }
        }
        if (mb_strlen($item->title) > $maxLengths['title']) {
            $issues[] = Issue::warning(Issue::TOO_LONG, 'title', ['%max%' => $maxLengths['title'], '%length%' => mb_strlen($item->title)]);
        }
        if (mb_strlen($item->description) > $maxLengths['description']) {
            $issues[] = Issue::warning(Issue::TOO_LONG, 'description', ['%max%' => $maxLengths['description'], '%length%' => mb_strlen($item->description)]);
        }
        if ($item->price <= 0) {
            $issues[] = Issue::error(Issue::MISSING_PRICE, 'price');
        }
        if (null !== $item->gtin && null === Gtin::normalize($item->gtin)) {
            $issues[] = Issue::warning(Issue::INVALID_GTIN, 'gtin', ['%gtin%' => $item->gtin]);
        }
        if (null === Gtin::normalize($item->gtin) && null === $item->mpn) {
            $issues[] = Issue::warning(Issue::NO_IDENTIFIERS, 'gtin');
        }

        return $issues;
    }

    /** Cuts a text to $max characters (formats truncate after the "too long" warning). */
    public static function cut(string $text, int $max): string
    {
        return mb_strlen($text) <= $max ? $text : rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }
}
