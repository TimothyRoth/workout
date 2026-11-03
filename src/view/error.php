<div class="wrapper">
    <div class="flex gap-10 align-center mt-20">
        <a class="flex" href="/"><img alt="zurück" src="/img/back.svg"/></a>
        <h2 class="m-0">Fehler <?= e($params['status']) ?></h2>
    </div>
    <p class="mt-20"><?= e($params['message']) ?></p>
    <a class="mt-20 inline-block" href="javascript:history.back()">Zurück</a>
</div>
