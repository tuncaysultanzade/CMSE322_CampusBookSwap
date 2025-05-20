<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/image_helper.php';

// Require login
requireLogin();

// Initialize variables
$error = '';
$success = false;

// Get all books for selection
$books = getDbConnection()->query("SELECT book_id, title, author FROM book ORDER BY title");
$books_array = [];
while ($book = $books->fetch_assoc()) {
    $books_array[] = [
        'id' => $book['book_id'],
        'title' => $book['title'],
        'author' => $book['author']
    ];
}

// Get all categories
$categories = getDbConnection()->query("SELECT * FROM category ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $book_id = $_POST['book_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $publisher = $_POST['publisher'] ?? '';
    $publisher_year = $_POST['publisher_year'] ?? '';
    $selected_categories = $_POST['categories'] ?? [];
    $listing_type = $_POST['listing_type'] ?? '';
    $condition = $_POST['condition'] ?? '';
    $price = $_POST['price'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Validate required fields
    if (!$listing_type || !$condition || ($listing_type === 'sale' && !$price)) {
        $error = 'Please fill in all required fields.';
    } else {
        $conn = getDbConnection();
        $conn->begin_transaction();
        
        try {
            // If new book
            if ($book_id === 'new') {
                if (!$title || !$author) {
                    throw new Exception('Book title and author are required for new books.');
                }
                
                // Insert new book
                $stmt = prepareStatement("
                    INSERT INTO book (title, author, publisher, publisher_year)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("sssi", $title, $author, $publisher, $publisher_year);
                $stmt->execute();
                $book_id = $stmt->insert_id;
                
                // Add categories
                if (!empty($selected_categories)) {
                    $stmt = prepareStatement("INSERT INTO book_category (book_id, cat_id) VALUES (?, ?)");
                    foreach ($selected_categories as $cat_id) {
                        $stmt->bind_param("ii", $book_id, $cat_id);
                        $stmt->execute();
                    }
                }
            }
            
            // Insert listing
            $stmt = prepareStatement("
                INSERT INTO listing (
                    book_id, user_id, price, `condition`, 
                    description, listing_type, list_date,
                    listing_status, admin_approved
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'inactive', 0)
            ");
            
            $user_id = getCurrentUserId();
            $price = $listing_type === 'sale' ? $price : null;
            
            $stmt->bind_param("iidsss", 
                $book_id,
                $user_id,
                $price,
                $condition,
                $description,
                $listing_type
            );
            $stmt->execute();
            $listing_id = $stmt->insert_id;
            
            // Handle image uploads
            if (!empty($_FILES['images']['name'][0])) {
                $upload_dir = UPLOAD_DIR;
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $stmt = prepareStatement("INSERT INTO listing_image (listing_id, image_url) VALUES (?, ?)");
                
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    try {
                        // Validate image
                        validateImage([
                            'tmp_name' => $tmp_name,
                            'size' => $_FILES['images']['size'][$key],
                            'error' => $_FILES['images']['error'][$key],
                            'name' => $_FILES['images']['name'][$key]
                        ]);
                        
                        // Generate unique filename
                        $file_ext = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                        $new_name = uniqid() . '.' . $file_ext;
                        $destination = $upload_dir . $new_name;
                        
                        // Compress and save image
                        if (compressAndSaveImage($tmp_name, $destination)) {
                            $image_url = '/uploads/' . $new_name;
                            $stmt->bind_param("is", $listing_id, $image_url);
                            $stmt->execute();
                        }
                    } catch (Exception $e) {
                        throw new Exception('Error processing image ' . ($_FILES['images']['name'][$key]) . ': ' . $e->getMessage());
                    }
                }
            }
            
            $conn->commit();
            $success = true;
            
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Listing added successfully! It will be visible after admin approval.'
            ];
            
            header('Location: listing.php?id=' . $listing_id);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Include header after all potential redirects
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="card-title text-center mb-4">Add New Listing</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <!-- Book Selection -->
                    <div class="mb-4">
                        <label class="form-label">Search for a Book</label>
                        <div class="position-relative">
                            <input type="text" class="form-control" id="book-search" 
                                   placeholder="Start typing to search books..." autocomplete="off">
                            <input type="hidden" name="book_id" id="selected-book-id" required>
                            <div class="dropdown-menu w-100" id="book-results" style="display: none;">
                                <!-- Results will be populated here -->
                            </div>
                        </div>
                        <div id="selected-book-info" class="mt-2" style="display: none;">
                            <div class="alert alert-info">
                                <h6 class="mb-1" id="selected-book-title"></h6>
                                <small class="text-muted" id="selected-book-author"></small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- New Book Fields (initially hidden) -->
                    <div id="new-book-fields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Book Title</label>
                            <input type="text" class="form-control" name="title">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Author</label>
                            <input type="text" class="form-control" name="author">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Publisher</label>
                            <input type="text" class="form-control" name="publisher">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Publication Year</label>
                            <input type="number" class="form-control" name="publisher_year" 
                                   min="1800" max="<?= date('Y') ?>">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Categories</label>
                            <div class="row g-3">
                                <?php while ($category = $categories->fetch_assoc()): ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="categories[]" value="<?= $category['cat_id'] ?>">
                                            <label class="form-check-label">
                                                <?= htmlspecialchars($category['name']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Listing Details -->
                    <div class="mb-3">
                        <label class="form-label">Listing Type</label>
                        <select class="form-select" name="listing_type" id="listing-type" required>
                            <option value="">Select type...</option>
                            <option value="sale">For Sale</option>
                            <option value="exchange">For Exchange</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Condition</label>
                        <select class="form-select" name="condition" required>
                            <option value="">Select condition...</option>
                            <option value="new">New</option>
                            <option value="like new">Like New</option>
                            <option value="very good">Very Good</option>
                            <option value="good">Good</option>
                            <option value="used">Used</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="price-field" style="display: none;">
                        <label class="form-label">Price (₺)</label>
                        <input type="number" class="form-control" name="price" step="0.01" min="0">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="4"></textarea>
                    </div>
                    
                    <!-- Image Upload -->
                    <div class="mb-4">
                        <label class="form-label">Images</label>
                        <input type="file" class="form-control custom-file-input" 
                               name="images[]" accept="image/jpeg,image/png" multiple
                               onchange="validateFileSize(this)">
                        <div class="form-text">
                            You can upload up to 5 images (JPG or PNG only, max 5MB each)
                        </div>
                        <div class="image-preview mt-2"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 btn-lg">
                        Add Listing
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const books = <?php echo json_encode($books_array); ?>;
    const bookSearch = document.getElementById('book-search');
    const bookResults = document.getElementById('book-results');
    const selectedBookId = document.getElementById('selected-book-id');
    const selectedBookInfo = document.getElementById('selected-book-info');
    const selectedBookTitle = document.getElementById('selected-book-title');
    const selectedBookAuthor = document.getElementById('selected-book-author');
    const newBookFields = document.getElementById('new-book-fields');
    const listingType = document.getElementById('listing-type');

    // Toggle price field based on listing type
    listingType.addEventListener('change', function() {
        const priceField = document.getElementById('price-field');
        if (this.value === 'sale') {
            priceField.style.display = 'block';
            priceField.querySelector('input[name="price"]').required = true;
        } else {
            priceField.style.display = 'none';
            priceField.querySelector('input[name="price"]').required = false;
            priceField.querySelector('input[name="price"]').value = '';
        }
    });
    
    // Trigger the change event to set initial state
    listingType.dispatchEvent(new Event('change'));

    function showResults(searchTerm) {
        bookResults.style.display = 'block';
        bookResults.innerHTML = '';

        // Filter books based on search term
        const filteredBooks = books.filter(book => 
            book.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
            book.author.toLowerCase().includes(searchTerm.toLowerCase())
        );

        // Always add "Add New Book" option
        bookResults.innerHTML = `
            <a class="dropdown-item" href="#" data-id="new">
                <i class="fas fa-plus-circle"></i> Add New Book
            </a>
            ${filteredBooks.length > 0 ? '<div class="dropdown-divider"></div>' : ''}
        `;

        // Add filtered books
        filteredBooks.forEach(book => {
            const item = document.createElement('a');
            item.className = 'dropdown-item';
            item.href = '#';
            item.innerHTML = `
                <strong>${book.title}</strong><br>
                <small class="text-muted">${book.author}</small>
            `;
            item.dataset.id = book.id;
            item.dataset.title = book.title;
            item.dataset.author = book.author;
            bookResults.appendChild(item);
        });
    }

    bookSearch.addEventListener('focus', () => {
        if (bookSearch.value.length > 0) {
            showResults(bookSearch.value);
        }
    });

    bookSearch.addEventListener('input', () => {
        showResults(bookSearch.value);
    });

    document.addEventListener('click', (e) => {
        if (!bookSearch.contains(e.target) && !bookResults.contains(e.target)) {
            bookResults.style.display = 'none';
        }
    });

    bookResults.addEventListener('click', (e) => {
        e.preventDefault();
        const item = e.target.closest('.dropdown-item');
        if (!item) return;

        const id = item.dataset.id;
        
        if (id === 'new') {
            selectedBookId.value = 'new';
            newBookFields.style.display = 'block';
            selectedBookInfo.style.display = 'none';
            bookSearch.value = 'Adding New Book';
            bookSearch.disabled = true;
        } else {
            selectedBookId.value = id;
            selectedBookTitle.textContent = item.dataset.title;
            selectedBookAuthor.textContent = item.dataset.author;
            newBookFields.style.display = 'none';
            selectedBookInfo.style.display = 'block';
            bookSearch.value = item.dataset.title;
        }
        
        bookResults.style.display = 'none';
    });
});

function validateFileSize(input) {
    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
    const files = input.files;
    
    for (let i = 0; i < files.length; i++) {
        if (files[i].size > maxSize) {
            alert('Error: Image file size must be less than 5MB. Please resize your image or choose a smaller one.');
            input.value = ''; // Clear the input
            return;
        }
    }
}
</script>

<style>
.dropdown-menu {
    max-height: 300px;
    overflow-y: auto;
}
.dropdown-item {
    white-space: normal;
    padding: 0.5rem 1rem;
}
.dropdown-item:hover {
    background-color: #f8f9fa;
}
</style>

<?php require_once 'includes/footer.php'; ?> 