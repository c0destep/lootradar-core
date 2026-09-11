<?php

declare(strict_types=1);

namespace LootRadar\Cli\Presentation;

use DateTimeImmutable;
use Exception;
use LootRadar\Cli\CliOptions;
use LootRadar\DTO\Theme;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Output\OutputInterface;

use function Termwind\parse;
use function Termwind\renderUsing;

/**
 * Renderiza as listagens humanas da CLI sem levar decisões de apresentação ao domínio.
 */
final readonly class OfferListRenderer
{
    public function __construct(
        private OutputInterface $output,
        private Theme $theme,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $games
     * @param list<string>               $failures
     */
    public function renderFreeGames(array $games, array $failures, CliOptions $options): void
    {
        $items = '';
        foreach ($games as $index => $game) {
            $title = self::escape(self::stringValue($game, 'title', 'Desconhecido'));
            $metadata = self::metadata([
                self::stringValue($game, 'storeName'),
                self::normalPrice($game),
                self::score($game),
                self::deadline($game),
            ]);
            $url = self::escape(self::stringValue($game, 'checkoutUrl'));
            $muted = self::style($this->theme, 'muted');
            $link = self::style($this->theme, 'link');

            $items .= "
                <div class='mt-1'>
                    <span class='font-bold'>" . ($index + 1) . ". {$title}</span>
                    <br/><span class='{$muted}'>{$metadata}</span>
                    <br/><span class='{$muted}'>Resgatar:</span> <span class='{$link}'>{$url}</span>
                </div>";
        }

        $emptyMessage = $failures === []
            ? 'Nenhum jogo gratuito encontrado no momento.'
            : 'Não foi possível confirmar jogos gratuitos nesta consulta.';

        $this->renderList(
            heading: 'JOGOS GRATUITOS',
            badge: self::quantity(count($games), 'JOGO', 'JOGOS'),
            context: $this->context($games, $options),
            items: $items,
            emptyMessage: $emptyMessage,
            failures: $failures,
        );
    }

    /**
     * @param list<array<string, mixed>> $deals
     * @param list<string>               $failures
     */
    public function renderDeals(array $deals, array $failures, CliOptions $options): void
    {
        $items = '';
        foreach ($deals as $index => $deal) {
            $title = self::escape(self::stringValue($deal, 'title', 'Desconhecido'));
            $discount = self::integerValue($deal, 'discountPercentage');
            $discountBadge = self::style($this->theme, 'discountBadge');
            $priceStyle = self::style($this->theme, 'price');
            $muted = self::style($this->theme, 'muted');
            $link = self::style($this->theme, 'link');
            $currentPrice = self::escape(self::price($deal, 'currentPrice', 'Preço indisponível'));
            $originalPrice = self::price($deal, 'originalPrice');
            $priceComparison = $originalPrice === ''
                ? ''
                : " <span class='{$muted}'>(antes " . self::escape($originalPrice) . ')</span>';
            $store = self::escape(self::stringValue($deal, 'storeName'));
            $details = self::metadata([self::score($deal), self::deadline($deal)]);
            $detailsLine = $details === '' ? '' : "<br/><span class='{$muted}'>{$details}</span>";
            $historyLine = $this->historyLine($deal);
            $url = self::escape(self::stringValue($deal, 'checkoutUrl'));

            $items .= "
                <div class='mt-1'>
                    <span class='font-bold'>" . ($index + 1) . ". {$title}</span>
                    <br/><span class='{$discountBadge}'>{$discount}% OFF</span>
                    <span class='{$priceStyle}'>{$currentPrice}</span>{$priceComparison}
                    <span class='{$muted}'> · {$store}</span>
                    {$historyLine}{$detailsLine}
                    <br/><span class='{$muted}'>Comprar:</span> <span class='{$link}'>{$url}</span>
                </div>";
        }

        $emptyMessage = $failures === []
            ? 'Nenhuma promoção encontrada no momento.'
            : 'Não foi possível confirmar promoções nesta consulta.';

        $this->renderList(
            heading: 'MAIORES DESCONTOS',
            badge: self::quantity(count($deals), 'OFERTA', 'OFERTAS'),
            context: $this->context($deals, $options),
            items: $items,
            emptyMessage: $emptyMessage,
            failures: $failures,
        );
    }

    /** @param list<string> $failures */
    private function renderList(
        string $heading,
        string $badge,
        string $context,
        string $items,
        string $emptyMessage,
        array $failures,
    ): void {
        $background = self::style($this->theme, 'bg');
        $headingStyle = self::style($this->theme, 'heading');
        $badgeStyle = self::style($this->theme, 'badge');
        $muted = self::style($this->theme, 'muted');
        $separator = self::style($this->theme, 'separator');
        $warning = $this->failureBlock($failures);
        $body = $items === '' ? "<div class='mt-1 {$muted}'>" . self::escape($emptyMessage) . '</div>' : $items;

        $markup = "<div>
            <span class='{$background} {$headingStyle}'>LOOTRADAR — "
            . self::escape($heading) . "</span>
            <br/><span class='{$badgeStyle}'>" . self::escape($badge) . "</span>
            <span class='{$muted}'> · " . self::escape($context) . "</span>
            {$warning}
            <hr class='{$separator}'/>
            {$body}
        </div>";

        renderUsing($this->output);
        try {
            $rendered = parse($markup);
            if (!$this->output->isDecorated()) {
                $rendered = Helper::removeDecoration($this->output->getFormatter(), $rendered);
            }

            $this->output->writeln(
                $rendered,
                $this->output->isDecorated() ? OutputInterface::OUTPUT_NORMAL : OutputInterface::OUTPUT_RAW,
            );
        } finally {
            renderUsing(null);
        }
    }

    /**
     * @param list<array<string, mixed>> $offers
     */
    private function context(array $offers, CliOptions $options): string
    {
        $stores = [];
        $currencies = [];
        foreach ($offers as $offer) {
            $store = self::stringValue($offer, 'storeName');
            if ($store !== '') {
                $stores[$store] = true;
            }

            $currency = self::stringValue($offer, 'currency');
            if ($currency !== '') {
                $currencies[$currency] = true;
            }
        }

        $parts = [];
        if ($stores !== []) {
            $parts[] = self::quantity(count($stores), 'loja', 'lojas');
        }

        $parts[] = "região {$options->country}";
        $parts[] = "score mínimo {$options->minimumScore}";

        if ($options->currency !== null) {
            $parts[] = "moeda {$options->currency}";
        } elseif (count($currencies) === 1) {
            $parts[] = 'moeda ' . array_key_first($currencies);
        } elseif (count($currencies) > 1) {
            $parts[] = 'moedas das lojas';
        }

        return implode(' · ', $parts);
    }

    /** @param list<string> $failures */
    private function failureBlock(array $failures): string
    {
        if ($failures === []) {
            return '';
        }

        $warning = self::style($this->theme, 'warning');
        $items = array_reduce(
            $failures,
            static fn(string $carry, string $failure): string => $carry
                . '<li>' . self::escape($failure) . '</li>',
            '',
        );

        return "
            <div class='mt-1 {$warning}'>
                <span class='font-bold'>Resultado parcial — Fontes indisponíveis nesta consulta:</span>
                " . self::quantity(count($failures), 'fonte indisponível', 'fontes indisponíveis') . ".
                <ul>{$items}</ul>
            </div>";
    }

    /** @param array<string, mixed> $deal */
    private function historyLine(array $deal): string
    {
        $historicalLow = self::price($deal, 'historicalLow');
        if ($historicalLow === '') {
            return '';
        }

        $style = self::style($this->theme, 'historicalLow');
        $message = ($deal['isAtHistoricalLow'] ?? false) === true
            ? 'MENOR PREÇO HISTÓRICO'
            : 'Menor preço histórico: ' . $historicalLow;

        return "<br/><span class='{$style}'>" . self::escape($message) . '</span>';
    }

    /** @param array<string, mixed> $offer */
    private static function normalPrice(array $offer): string
    {
        $amount = $offer['originalPrice'] ?? null;
        if ((!is_int($amount) && !is_float($amount)) || $amount <= 0) {
            return '';
        }

        $price = self::price($offer, 'originalPrice');

        return $price === '' ? '' : "Preço normal: {$price}";
    }

    /** @param array<string, mixed> $offer */
    private static function score(array $offer): string
    {
        $score = $offer['approvalRating'] ?? null;

        return is_int($score) ? "Avaliação: {$score}/100" : '';
    }

    /** @param array<string, mixed> $offer */
    private static function deadline(array $offer): string
    {
        $expiresAt = $offer['expiresAt'] ?? null;
        if (!is_string($expiresAt) || trim($expiresAt) === '') {
            return '';
        }

        try {
            $date = new DateTimeImmutable($expiresAt);
        } catch (Exception) {
            return '';
        }

        $timezone = $date->format('T');

        return 'Até: ' . $date->format('d/m/Y H:i ') . ($timezone === 'Z' ? 'UTC' : $timezone);
    }

    /** @param array<string, mixed> $offer */
    private static function price(array $offer, string $key, string $fallback = ''): string
    {
        $amount = $offer[$key] ?? null;
        if (!is_int($amount) && !is_float($amount)) {
            return $fallback;
        }

        $currency = self::stringValue($offer, 'currency');

        return trim(number_format((float) $amount, 2, ',', '.') . " {$currency}");
    }

    /** @param array<string, mixed> $offer */
    private static function stringValue(array $offer, string $key, string $fallback = ''): string
    {
        $value = $offer[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    /** @param array<string, mixed> $offer */
    private static function integerValue(array $offer, string $key): int
    {
        $value = $offer[$key] ?? null;

        return is_int($value) ? $value : 0;
    }

    /** @param list<string> $parts */
    private static function metadata(array $parts): string
    {
        $present = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));

        return self::escape(implode(' · ', $present));
    }

    private static function quantity(int $count, string $singular, string $plural): string
    {
        return $count . ' ' . ($count === 1 ? $singular : $plural);
    }

    private static function style(Theme $theme, string $key): string
    {
        return self::escape($theme->styles[$key] ?? '');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars(OutputFormatter::escape($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
