<?php
declare(strict_types=1);

/**
 * @var array|null $results
 */

require_once __DIR__ . '/../layout.php';
?>

<div class="container mx-auto mt-8">
    <h1 class="text-2xl font-bold mb-4">Import Users</h1>

    <div class="mb-4">
        <a href="/public/index.php?r=admin_users_import_template"
           class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Download CSV Template
        </a>
    </div>

    <form action="/public/index.php?r=admin_users_import_process" method="post" enctype="multipart/form-data"
          class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <?php csrf_input(); ?>
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="csv_file">
                CSV File
            </label>
            <input type="file" name="csv_file" id="csv_file"
                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                   required>
        </div>
        <div class="flex items-center justify-between">
            <button type="submit"
                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Import Users
            </button>
        </div>
    </form>

    <?php if ($results): ?>
        <div class="mt-8">
            <h2 class="text-xl font-bold mb-4">Import Results</h2>

            <?php if (!empty($results['success'])): ?>
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-green-600">Successfully Imported Users</h3>
                    <div class="bg-white shadow-md rounded my-6">
                        <table class="min-w-full leading-normal">
                            <thead>
                            <tr>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Email
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Temporary Password
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($results['success'] as $success): ?>
                                <tr>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap"><?= html_escape($success['email']) ?></p>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap"><?= html_escape($success['temp_password']) ?></p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($results['error'])): ?>
                <div>
                    <h3 class="text-lg font-semibold text-red-600">Failed Imports</h3>
                     <div class="bg-white shadow-md rounded my-6">
                        <table class="min-w-full leading-normal">
                            <thead>
                            <tr>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Email
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Reason
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($results['error'] as $error): ?>
                                <tr>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap"><?= html_escape($error['email'] ?? 'N/A') ?></p>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap"><?= html_escape($error['reason']) ?></p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    <?php endif; ?>

</div>
