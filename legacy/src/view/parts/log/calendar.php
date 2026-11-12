<?php
/** Training calendar card: weekly streak, counts and a 16-week heatmap (js/calendar.js shows a tapped day). */
$calendar = \App\Calendar::build($logs);
?>
<section class="card calendar" aria-label="Trainingskalender">
    <div class="stats stats-3">
        <div class="stat"><b><?= e($calendar['streak']) ?></b><span><?= $calendar['streak'] === 1 ? 'Woche in Folge' : 'Wochen in Folge' ?></span></div>
        <div class="stat"><b><?= e($calendar['thisWeek']) ?></b><span>diese Woche</span></div>
        <div class="stat"><b><?= e($calendar['lastFourWeeks']) ?></b><span>letzte 4 Wochen</span></div>
    </div>
    <div class="cal">
        <div class="cal-months">
            <?php foreach ($calendar['months'] as $month) { ?>
                <span style="grid-column: <?= $month['col'] + 1 ?>"><?= e($month['label']) ?></span>
            <?php } ?>
        </div>
        <div class="cal-body">
            <div class="cal-weekdays"><span>Mo</span><span></span><span>Mi</span><span></span><span>Fr</span><span></span><span></span></div>
            <div class="cal-grid" role="grid">
                <?php foreach ($calendar['weeks'] as $week) {
                    foreach ($week as $day) {
                        $level = $day['future'] ? 'is-future' : ($day['count'] === 0 ? '' : ($day['count'] === 1 ? 'cal-1' : 'cal-2'));
                        $title = $day['label'] . ($day['count'] > 0 ? ' · ' . implode(', ', $day['names']) : '');
                        ?><button type="button" class="cal-day <?= e(trim($level . ($day['today'] ? ' is-today' : ''))) ?>" data-cal-day="<?= e($title) ?>" aria-label="<?= e($title) ?>"<?= $day['future'] ? ' disabled' : '' ?>></button><?php
                    }
                } ?>
            </div>
        </div>
        <p class="cal-caption hint" data-cal-caption>Tippe auf einen Tag.</p>
    </div>
</section>
