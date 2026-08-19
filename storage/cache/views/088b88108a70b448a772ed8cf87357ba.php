<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->yieldSection('title', 'Trip App'); ?></title>

    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 2rem; background: #f8fafc; color: #1e293b; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>

    <?= $this->yieldStack('styles'); ?>
</head>
<body>
    <div class="container">
        <?= $this->yieldSection('content'); ?>
    </div>

    <?= $this->yieldStack('scripts'); ?>
</body>
</html>