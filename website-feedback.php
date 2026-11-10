<?php
require_once __DIR__ . '/config/functions.php';
requireRole([ROLE_ADMIN]);

$title = 'Website Feedback';
$filter = $_GET['filter'] ?? 'pending';
if (!in_array($filter, ['pending', 'approved', 'all'], true)) {
    $filter = 'pending';
}
$typeFilter = $_GET['type'] ?? 'all';
if (!in_array($typeFilter, ['all', 'review', 'inquiry'], true)) {
    $typeFilter = 'all';
}
$ratingFilter = $_GET['rating'] ?? 'all';
if ($ratingFilter !== 'all' && !in_array($ratingFilter, ['1', '2', '3', '4', '5'], true)) {
    $ratingFilter = 'all';
}
$search = trim($_GET['q'] ?? '');
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$filterParams = ['filter' => $filter, 'type' => $typeFilter, 'rating' => $ratingFilter, 'q' => $search];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $feedbackId = (int)($_POST['feedback_id'] ?? 0);
        $approved = ($_POST['is_approved'] ?? '') === '1' ? 1 : 0;

        try {
            $feedback = db()->fetchOne('SELECT id FROM website_feedback WHERE id = :id', [':id' => $feedbackId]);
            if (!$feedback) {
                throw new Exception('Feedback not found.');
            }

            db()->query(
                'UPDATE website_feedback SET is_approved = :approved WHERE id = :id',
                [':approved' => $approved, ':id' => $feedbackId]
            );
            setFlash('success', $approved ? 'Feedback approved and available to the website.' : 'Feedback approval removed.');
            header('Location: ' . BASE_URL . '/feedback.php?' . http_build_query($filterParams));
            exit;
        } catch (Exception $exception) {
            $error = $exception->getMessage();
        }
    }
}

$counts = db()->fetchOne(
    'SELECT COUNT(*) AS total, SUM(is_approved = 0) AS pending, SUM(is_approved = 1) AS approved FROM website_feedback'
);
$conditions = [];
$params = [];
if ($filter === 'pending') {
    $conditions[] = 'is_approved = 0';
} elseif ($filter === 'approved') {
    $conditions[] = 'is_approved = 1';
}
if ($typeFilter !== 'all') {
    $conditions[] = 'type = :type';
    $params[':type'] = $typeFilter;
}
if ($ratingFilter !== 'all') {
    $conditions[] = 'rating = :rating';
    $params[':rating'] = (int)$ratingFilter;
}
if ($search !== '') {
    $conditions[] = '(name LIKE :search_name OR email LIKE :search_email OR subject LIKE :search_subject OR message LIKE :search_message)';
    $searchTerm = '%' . $search . '%';
    $params[':search_name'] = $searchTerm;
    $params[':search_email'] = $searchTerm;
    $params[':search_subject'] = $searchTerm;
    $params[':search_message'] = $searchTerm;
}
$whereSql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$countRow = db()->fetchOne('SELECT COUNT(*) AS total FROM website_feedback' . $whereSql, $params);
$totalFeedback = (int)($countRow['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalFeedback / $perPage));
$page = min($page, $totalPages);
$feedbackItems = db()->fetchAll(
    'SELECT * FROM website_feedback' . $whereSql . ' ORDER BY is_approved ASC, created_at DESC LIMIT ' . (($page - 1) * $perPage) . ', ' . $perPage,
    $params
);
$success = getFlash('success');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#0c0c0e]">
    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-white">Website Feedback</h2>
                <p class="text-xs text-stone-400">Review submissions and control which reviews are approved on the website.</p>
            </div>
            <div class="flex gap-3 text-xs">
                <span class="px-3 py-2 rounded-xl bg-amber-950/60 border border-amber-900 text-amber-300">Pending: <?= (int)($counts['pending'] ?? 0) ?></span>
                <span class="px-3 py-2 rounded-xl bg-emerald-950/60 border border-emerald-900 text-emerald-300">Approved: <?= (int)($counts['approved'] ?? 0) ?></span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="p-3.5 bg-red-950 border border-red-800 rounded-2xl text-red-300 text-xs"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="p-3.5 bg-emerald-950 border border-emerald-800 rounded-2xl text-emerald-300 text-xs"><?= e($success) ?></div>
        <?php endif; ?>

        <nav class="flex gap-2 border-b border-stone-800" aria-label="Feedback status filter">
            <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'all' => 'All feedback'] as $filterValue => $label): ?>
                <a href="<?= BASE_URL ?>/feedback.php?<?= e(http_build_query(array_merge($filterParams, ['filter' => $filterValue]))) ?>" class="px-4 py-2.5 text-xs font-bold border-b-2 <?= $filter === $filterValue ? 'border-red-500 text-white' : 'border-transparent text-stone-400 hover:text-white' ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <form method="GET" action="feedback.php" class="flex flex-col sm:flex-row gap-3 p-4 bg-stone-900 border border-stone-800 rounded-2xl">
            <input type="hidden" name="filter" value="<?= e($filter) ?>">
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, subject, or message" class="flex-1 min-w-48 px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
            <select name="type" class="px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All types</option>
                <option value="review" <?= $typeFilter === 'review' ? 'selected' : '' ?>>Reviews</option>
                <option value="inquiry" <?= $typeFilter === 'inquiry' ? 'selected' : '' ?>>Inquiries</option>
            </select>
            <select name="rating" class="px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                <option value="all" <?= $ratingFilter === 'all' ? 'selected' : '' ?>>All ratings</option>
                <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                    <option value="<?= $rating ?>" <?= $ratingFilter === (string)$rating ? 'selected' : '' ?>><?= $rating ?> stars</option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-xl text-xs font-bold">Filter</button>
            <?php if ($search !== '' || $typeFilter !== 'all' || $ratingFilter !== 'all'): ?>
                <a href="<?= BASE_URL ?>/feedback.php?filter=<?= e($filter) ?>" class="px-4 py-2 bg-stone-800 hover:bg-stone-700 text-stone-300 rounded-xl text-xs font-bold text-center">Clear</a>
            <?php endif; ?>
        </form>

        <div class="bg-stone-900 border border-stone-800 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-stone-950 text-stone-400 font-bold uppercase text-[10px] border-b border-stone-800">
                        <tr>
                            <th class="p-3.5">Feedback</th>
                            <th class="p-3.5">Type</th>
                            <th class="p-3.5">Rating</th>
                            <th class="p-3.5">Submitted</th>
                            <th class="p-3.5">Status</th>
                            <th class="p-3.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800">
                        <?php foreach ($feedbackItems as $item): ?>
                            <tr class="align-top hover:bg-stone-950/70">
                                <td class="p-3.5 min-w-64">
                                    <span class="font-bold text-white"><?= e($item['name']) ?></span>
                                    <?php if (!empty($item['email'])): ?><a class="block text-[10px] text-stone-400 hover:text-red-300" href="mailto:<?= e($item['email']) ?>"><?= e($item['email']) ?></a><?php endif; ?>
                                    <?php if (!empty($item['subject'])): ?><span class="block mt-2 font-semibold text-stone-300"><?= e($item['subject']) ?></span><?php endif; ?>
                                    <p class="mt-1 whitespace-pre-wrap text-stone-400"><?= e($item['message']) ?></p>
                                </td>
                                <td class="p-3.5"><span class="px-2 py-1 rounded-lg bg-stone-800 text-stone-300"><?= e(ucfirst($item['type'])) ?></span></td>
                                <td class="p-3.5 text-amber-300"><?= (int)$item['rating'] ?> / 5</td>
                                <td class="p-3.5 whitespace-nowrap text-stone-400"><?= e(formatDate($item['created_at'])) ?></td>
                                <td class="p-3.5"><span class="px-2 py-1 rounded-full <?= (int)$item['is_approved'] === 1 ? 'bg-emerald-950 text-emerald-300' : 'bg-amber-950 text-amber-300' ?>"><?= (int)$item['is_approved'] === 1 ? 'Approved' : 'Pending' ?></span></td>
                                <td class="p-3.5 text-right">
                                    <form method="POST" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="feedback_id" value="<?= (int)$item['id'] ?>">
                                        <input type="hidden" name="is_approved" value="<?= (int)$item['is_approved'] === 1 ? '0' : '1' ?>">
                                        <button type="submit" class="px-3 py-2 rounded-lg font-bold <?= (int)$item['is_approved'] === 1 ? 'bg-stone-800 text-stone-300 hover:bg-stone-700' : 'bg-emerald-700 text-white hover:bg-emerald-600' ?>">
                                            <?= (int)$item['is_approved'] === 1 ? 'Unapprove' : 'Approve' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($feedbackItems)): ?>
                            <tr><td colspan="6" class="p-8 text-center text-stone-500">No feedback in this view.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= renderPagination($page, $totalFeedback, $perPage, $filterParams, true) ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>