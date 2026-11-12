<?php

declare(strict_types=1);

namespace App\Twig;

use App\Asset\AppVersion;
use App\Enum\Unit;
use App\Training\Format;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * View helpers of the app: inline SVG icons, the stepper wrapper, the import map for public/js and the
 * German number, date and set formats.
 */
final class AppExtension extends AbstractExtension
{
    /** 24px grid, stroked with currentColor. */
    private const ICONS = [
        'back'    => '<path d="M15 18l-6-6 6-6"/>',
        'chevron' => '<path d="M9 18l6-6-6-6"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'minus'   => '<path d="M5 12h14"/>',
        'close'   => '<path d="M6 6l12 12M6 18L18 6"/>',
        'edit'    => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'play'    => '<path d="M7 4v16l13-8z" fill="currentColor" stroke="none"/>',
        'check'   => '<path d="M20 6 9 17l-5-5"/>',
        'trash'   => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2"/>',
        'timer'   => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M9 2h6"/>',
        'copy'    => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
        'phone'   => '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>',
        'share'   => '<path d="M12 3v12M8 7l4-4 4 4"/><path d="M4 13v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/>',
        'more'    => '<circle cx="12" cy="5" r="1" fill="currentColor"/><circle cx="12" cy="12" r="1" fill="currentColor"/><circle cx="12" cy="19" r="1" fill="currentColor"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'lock'    => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'eye'     => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M3 3l18 18"/><path d="M10.6 10.6a3 3 0 0 0 4.2 4.2"/><path d="M9.9 5.2A10.7 10.7 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17.4 17.4 0 0 0 2 12s3.5 7 10 7a10 10 0 0 0 4.4-1"/>',
        'logout'  => '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M13 4h6a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-6"/>',
        'alert'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>',
        'grip'    => '<circle cx="9" cy="6" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="6" r="1.2" fill="currentColor" stroke="none"/><circle cx="9" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="9" cy="18" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="18" r="1.2" fill="currentColor" stroke="none"/>',
        'note'    => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
        'offline' => '<path d="M2 2l20 20"/><path d="M8.5 16.5a5 5 0 0 1 7 0M5 13a10 10 0 0 1 5.2-2.8M12 20h.01"/><path d="M16.7 11.5A10 10 0 0 1 19 13M1.4 8.6A16 16 0 0 1 8 5M22.6 8.6a16 16 0 0 0-8-4.3"/>',
    ];

    public function __construct(
        private readonly AppVersion $version,
        #[Autowire('%kernel.project_dir%/public')] private readonly string $publicDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('icon', $this->icon(...), ['is_safe' => ['html']]),
            new TwigFunction('stepper', $this->stepper(...), ['is_safe' => ['html']]),
            new TwigFunction('import_map', $this->importMap(...), ['is_safe' => ['html']]),
            new TwigFunction('unit_cases', Unit::cases(...)),
            new TwigFunction('app_version', $this->version->current(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_number', Format::number(...)),
            new TwigFilter('local_datetime', $this->localDatetime(...)),
            new TwigFilter('workout_subtitle', $this->workoutSubtitle(...)),
            new TwigFilter('set_label', $this->setLabel(...)),
            new TwigFilter('exercise_summary', $this->exerciseSummary(...)),
            new TwigFilter('unit', static fn (mixed $value): Unit => $value instanceof Unit ? $value : (Unit::tryFrom((string) $value) ?? Unit::Kg)),
        ];
    }

    public function icon(string $name, string $class = ''): string
    {
        return \sprintf(
            '<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
            self::escape(trim('icon '.$class)),
            self::ICONS[$name] ?? ''
        );
    }

    /** Wraps an already rendered numeric <input data-step="..."> in minus/plus buttons (see js/stepper.js). */
    public function stepper(string $input, string $class = ''): string
    {
        return \sprintf(
            '<div class="%s"><button class="stepper-btn" type="button" data-step="-1" aria-label="Weniger" tabindex="-1">%s</button>%s<button class="stepper-btn" type="button" data-step="1" aria-label="Mehr" tabindex="-1">%s</button></div>',
            self::escape(trim('stepper '.$class)),
            $this->icon('minus', 'icon-sm'),
            $input,
            $this->icon('plus', 'icon-sm')
        );
    }

    /** Maps every module in public/js to its versioned URL, so `import … from "app/session"` is cache-busted too. */
    public function importMap(): string
    {
        $imports = [];
        foreach (glob($this->publicDir.'/js/*.js') ?: [] as $file) {
            $name = basename($file, '.js');
            $imports['app/'.$name] = '/js/'.$name.'.js?v='.$this->version->current();
        }

        return json_encode(['imports' => $imports], \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_THROW_ON_ERROR);
    }

    /** A UTC "Y-m-d H:i:s" timestamp (as SQLite stores it) in local time. */
    public function localDatetime(?string $utc, string $format = 'd.m.Y H:i'): string
    {
        if (null === $utc || '' === $utc) {
            return '';
        }
        try {
            return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format($format);
        } catch (\Exception) {
            return $utc;
        }
    }

    /** "Zuletzt gestern · 12 Trainings" or "Noch nie trainiert". */
    public function workoutSubtitle(array $workout): string
    {
        $count = (int) ($workout['session_count'] ?? 0);
        if (0 === $count || empty($workout['last_trained_at'])) {
            return 'Noch nie trainiert';
        }

        return 'Zuletzt '.$this->relativeDay($workout['last_trained_at']).' · '.$count.(1 === $count ? ' Training' : ' Trainings');
    }

    /** Label of a planned or logged set: "80 kg", "Körpergewicht", "60 s". */
    public function setLabel(array $set): string
    {
        return (Unit::tryFrom((string) ($set['unit'] ?? '')) ?? Unit::Kg)->format($set['load'] ?? null);
    }

    /**
     * One line for a collapsed exercise: "3 × 8 · 80 kg · 90 s", with ranges ("3 × 8–12 · 60–80 kg")
     * when the sets differ and "gemischt" when the units do.
     */
    public function exerciseSummary(array $sets): string
    {
        $count = \count($sets);
        if (0 === $count) {
            return 'Noch keine Sätze';
        }

        $range = static function (array $values, string $suffix = ''): string {
            $min = min($values);
            $max = max($values);

            return ($min === $max ? Format::number($min) : Format::number($min).'–'.Format::number($max)).$suffix;
        };

        $units = array_values(array_unique(array_map(static fn (array $set): string => (string) ($set['unit'] ?? ''), $sets)));
        if (1 === \count($units)) {
            $unit = Unit::tryFrom($units[0]) ?? Unit::Kg;
            $loads = array_map(static fn (array $set): float => (float) ($set['load'] ?? 0), $sets);
            $load = !$unit->needsLoad() ? $unit->short(null)
                : (min($loads) === max($loads) ? $unit->format(min($loads)) : Format::number(min($loads)).'–'.$unit->format(max($loads)));
        } else {
            $load = 'gemischt';
        }

        $reps = $range(array_map(static fn (array $set): int => (int) $set['repetitions'], $sets));
        $rest = $range(array_map(static fn (array $set): int => (int) $set['rest_time'], $sets), ' s');

        return "$count × $reps · $load · $rest";
    }

    /** "heute", "gestern", "vor 3 Tagen", "vor 2 Wochen", "vor 4 Monaten", "vor 1 Jahr". */
    private function relativeDay(string $utc): string
    {
        try {
            $day = (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->setTime(0, 0);
        } catch (\Exception) {
            return $utc;
        }
        $days = (int) $day->diff(new \DateTimeImmutable('today'))->format('%r%a');

        return match (true) {
            $days <= 0 => 'heute',
            1 === $days => 'gestern',
            $days < 7 => "vor $days Tagen",
            $days < 30 => 1 === intdiv($days, 7) ? 'vor 1 Woche' : 'vor '.intdiv($days, 7).' Wochen',
            $days < 365 => 1 === intdiv($days, 30) ? 'vor 1 Monat' : 'vor '.intdiv($days, 30).' Monaten',
            default => 1 === intdiv($days, 365) ? 'vor 1 Jahr' : 'vor '.intdiv($days, 365).' Jahren',
        };
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
