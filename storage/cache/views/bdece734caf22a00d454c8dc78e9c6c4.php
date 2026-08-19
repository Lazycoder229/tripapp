<?php $this->layout = 'layouts.app'; ?>

<?php $this->setSection('title', 'About — URL Test'); ?>

<?php $this->startSection('content'); ?>
    <h1>Testing @url directive</h1>
    <ul>
        <li><a href="<?= $this->url('about'); ?>">Relative path: about</a></li>
        <li><a href="<?= $this->url('/contact'); ?>">Leading slash: /contact</a></li>
        <li><a href="<?= $this->url(); ?>">Base URL only (home)</a></li>
        <li><a href="<?= $this->url('https://google.com'); ?>">Already absolute (should stay as-is)</a></li>
    </ul>

    <p>Raw output test:</p>
    <pre><?= htmlspecialchars((string)('URL: ' . $urlValue), ENT_QUOTES, 'UTF-8'); ?></pre>
<?php $this->endSection(); ?>