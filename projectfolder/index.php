<?php
require_once 'includes/header.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$condition = $_GET['condition'] ?? '';
$type = $_GET['type'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;

// Build the base query
$query = "
    SELECT l.*, b.title as book_title, b.author, b.publisher_year, 
           u.name as seller_name, 
           MIN(li.image_url) as thumbnail,
           COUNT(DISTINCT f.user_id) as favorite_count
    FROM listing l
    LEFT JOIN book b ON l.book_id = b.book_id
    LEFT JOIN user u ON l.user_id = u.user_id
    LEFT JOIN listing_image li ON l.listing_id = li.listing_id
    LEFT JOIN favorite f ON l.listing_id = f.listing_id
    WHERE l.admin_approved = 1 
    AND l.listing_status = 'active'
";

$params = [];
$types = "";

// Add search filter
if ($search) {
    $query .= " AND (b.title LIKE ? OR b.author LIKE ? OR l.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= "sss";
}

// Add category filter
if ($category) {
    $query .= " AND EXISTS (
        SELECT 1 FROM book_category bc 
        WHERE bc.book_id = b.book_id 
        AND bc.cat_id = ?
    )";
    $params[] = $category;
    $types .= "i";
}

// Add condition filter
if ($condition) {
    $query .= " AND l.condition = ?";
    $params[] = $condition;
    $types .= "s";
}

// Add type filter
if ($type) {
    $query .= " AND l.listing_type = ?";
    $params[] = $type;
    $types .= "s";
}

// Group by and order
$query .= " GROUP BY l.listing_id ORDER BY l.list_date DESC";

// Get total count for pagination
$count_query = str_replace("SELECT l.*, b.title", "SELECT COUNT(DISTINCT l.listing_id)", $query);
$stmt = prepareStatement($count_query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_items = $stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_items / $per_page);

// Add pagination
$query .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = ($page - 1) * $per_page;
$types .= "ii";

// Execute final query
$stmt = prepareStatement($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$listings = $stmt->get_result();

// Get categories for filter
$categories = getDbConnection()->query("SELECT * FROM category ORDER BY name");
?>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-md-12">
        <form action="" method="GET" class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search books..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= $cat['cat_id'] ?>" <?= $category == $cat['cat_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="condition" class="form-select">
                            <option value="">All Conditions</option>
                            <option value="new" <?= $condition === 'new' ? 'selected' : '' ?>>New</option>
                            <option value="like new" <?= $condition === 'like new' ? 'selected' : '' ?>>Like New</option>
                            <option value="very good" <?= $condition === 'very good' ? 'selected' : '' ?>>Very Good</option>
                            <option value="good" <?= $condition === 'good' ? 'selected' : '' ?>>Good</option>
                            <option value="used" <?= $condition === 'used' ? 'selected' : '' ?>>Used</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="sale" <?= $type === 'sale' ? 'selected' : '' ?>>For Sale</option>
                            <option value="exchange" <?= $type === 'exchange' ? 'selected' : '' ?>>For Exchange</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Listings Grid -->
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
    <?php while ($listing = $listings->fetch_assoc()): ?>
        <div class="col">
            <div class="card h-100">
                <a href="/listing.php?id=<?= $listing['listing_id'] ?>" class="text-decoration-none text-dark">
                    <div class="position-relative">
                        <?php if ($listing['thumbnail']): ?>
                            <img src="<?= htmlspecialchars($listing['thumbnail']) ?>" class="card-img-top" alt="Book cover" style="height: 300px; object-fit: contain;">
                        <?php else: ?>
                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 300px;">
                                <i class="fas fa-book fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($listing['listing_type'] === 'exchange'): ?>
                            <span class="position-absolute top-0 end-0 badge bg-info m-2">
                                <i class="fas fa-exchange-alt"></i> Exchange
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body">
                        <h5 class="card-title text-truncate">
                            <?= htmlspecialchars($listing['book_title']) ?>
                        </h5>
                        <p class="card-text mb-1">
                            <small class="text-muted">by <?= htmlspecialchars($listing['author']) ?></small>
                        </p>
                        <?php if ($listing['listing_type'] === 'sale'): ?>
                            <p class="card-text text-primary fw-bold">
                                <?= number_format($listing['price'], 2) ?> ₺
                            </p>
                        <?php endif; ?>
                        <p class="card-text">
                            <small class="text-muted">
                                Condition: <?= ucfirst($listing['condition']) ?>
                            </small>
                        </p>
                    </div>
                </a>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="fas fa-user"></i> 
                            <?= htmlspecialchars($listing['seller_name']) ?>
                        </small>
                        <small class="text-muted">
                            <i class="fas fa-heart"></i> <?= $listing['favorite_count'] ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                    Previous
                </a>
            </li>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
            
            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                    Next
                </a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?> 