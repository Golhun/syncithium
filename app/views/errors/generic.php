<?php
/**
 * @var int $code
 * @var string $title
 * @var string $message
 * @var string $joke
 */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo e($title ?? 'Error'); ?> (<?php echo e($code ?? ''); ?>)</title>
  <link rel="stylesheet" href="/public/assets/css/tailwind.min.css">
</head>
<body class="bg-white">
    <section class="bg-white">
        <div class="py-8 px-4 mx-auto max-w-screen-xl lg:py-16 lg:px-6">
            <div class="mx-auto max-w-screen-sm text-center">
                <h1 class="mb-4 text-7xl tracking-tight font-extrabold lg:text-9xl text-sky-600"><?= e($code ?? 'Error') ?></h1>
                <p class="mb-4 text-3xl tracking-tight font-bold text-gray-900 md:text-4xl"><?= e($title ?? 'Error') ?></p>
                <p class="mb-4 text-lg font-light text-gray-500"><?= e($message ?? 'An unexpected error occurred.') ?> <br><span class="italic"><?= e($joke ?? '') ?></span></p>
                <a href="/public/index.php" class="inline-flex text-white bg-sky-600 hover:bg-sky-800 focus:ring-4 focus:outline-none focus:ring-sky-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center my-4">Back to Homepage</a>
            </div>   
        </div>
    </section>
</body>
</html>