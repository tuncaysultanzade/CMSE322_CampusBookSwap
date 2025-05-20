SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `book` (
  `book_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(100) DEFAULT NULL,
  `publisher_year` int(11) DEFAULT NULL,
  `publisher` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

INSERT INTO `book` (`book_id`, `title`, `author`, `publisher_year`, `publisher`) VALUES
(23, 'Veronica Ölmek İstiyor', 'Paulo Coelho', 2000, 'Test Publisher'),
(24, 'Fatih Sultan Mehmed: Doğu\'nun ve Batı\'nın Efendisi', 'İlber Ortaylı', 2000, 'Test Corp.'),
(25, 'Rezonans Kanunu', 'Pierre Franckh', 2000, 'Test Publisher'),
(26, 'El Kızı', 'Orhan Kemal', 2000, 'Test Publisher'),
(27, 'Günübirlik Hayatlar', 'Irvin D. Yalom', 2000, 'Test Publisher'),
(28, 'Silver in the Bone', 'Alexandra Bracken', 2000, 'Hachette Children'),
(29, 'Beautiful World Where Are You : A Novel', 'Sally Rooney', 2000, 'Test Publisher'),
(30, 'Turkish Cuisine', 'Kolektif', 2000, 'Kütüphaneler ve Yayımlar Genel Müd.');

CREATE TABLE `book_category` (
  `book_id` int(11) NOT NULL,
  `cat_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

INSERT INTO `book_category` (`book_id`, `cat_id`) VALUES
(23, 16),
(23, 31),
(23, 41),
(24, 18),
(25, 17),
(25, 18),
(26, 29),
(26, 43),
(27, 29),
(27, 43),
(28, 42),
(28, 43),
(29, 27),
(30, 18);

CREATE TABLE `category` (
  `cat_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

INSERT INTO `category` (`cat_id`, `name`) VALUES
(1, 'Computer Science'),
(2, 'Engineering'),
(3, 'Mathematics'),
(4, 'Physics'),
(5, 'Biology'),
(6, 'Chemistry'),
(7, 'Medicine'),
(8, 'Law'),
(9, 'Economics'),
(10, 'Business & Management'),
(11, 'Accounting'),
(12, 'Finance'),
(13, 'Marketing'),
(14, 'Psychology'),
(15, 'Sociology'),
(16, 'Philosophy'),
(17, 'Political Science'),
(18, 'History'),
(19, 'Education'),
(20, 'Language & Linguistics'),
(21, 'Architecture'),
(22, 'Environmental Science'),
(23, 'Statistics'),
(24, 'Data Science'),
(25, 'Machine Learning'),
(26, 'Fiction'),
(27, 'Non-Fiction'),
(28, 'Science Fiction'),
(29, 'Fantasy'),
(30, 'Mystery & Thriller'),
(31, 'Romance'),
(32, 'Biography & Memoir'),
(33, 'Self-help'),
(34, 'Health & Fitness'),
(35, 'Cookbooks'),
(36, 'Travel'),
(37, 'Art & Photography'),
(38, 'Comics & Graphic Novels'),
(39, 'Children\'s Books'),
(40, 'Young Adult'),
(41, 'Poetry'),
(42, 'Religion & Spirituality'),
(43, 'Parenting & Relationships'),
(44, 'Hobbies & Crafts'),
(45, 'Music'),
(46, 'Sports'),
(47, 'Technology & Gadgets'),
(48, 'DIY & Home Improvement'),
(49, 'Career & Personal Development'),
(50, 'Test Preparation & Study Guides');

CREATE TABLE `favorite` (
  `user_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

INSERT INTO `favorite` (`user_id`, `listing_id`) VALUES
(12, 30),
(12, 33),
(12, 34);

CREATE TABLE `listing` (
  `listing_id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `condition` varchar(50) DEFAULT NULL,
  `listing_status` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `listing_type` varchar(20) DEFAULT NULL CHECK (`listing_type` in ('sale','exchange')),
  `list_date` datetime DEFAULT NULL,
  `admin_approved` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

INSERT INTO `listing` (`listing_id`, `book_id`, `user_id`, `title`, `price`, `condition`, `listing_status`, `description`, `listing_type`, `list_date`, `admin_approved`) VALUES
(28, 23, 11, NULL, 250.00, 'new', 'inactive', 'It is brand new book.', 'sale', '2025-05-18 19:17:10', 0),
(29, 24, 11, NULL, 0.00, 'like new', 'active', 'The books language is Turkish. ', 'exchange', '2025-05-18 19:19:30', 1),
(30, 25, 11, NULL, NULL, 'new', 'active', 'The book is in turkish.', 'exchange', '2025-05-18 19:28:38', 1),
(31, 26, 11, NULL, 180.00, 'very good', 'sold', 'I have read it and want to sell.', 'sale', '2025-05-18 19:30:00', 1),
(32, 27, 11, NULL, NULL, 'used', 'active', 'I want to exchange with any other book please message me.', 'exchange', '2025-05-18 19:31:23', 1),
(33, 28, 11, NULL, 1300.00, 'new', 'active', 'Its been signed book that is why its so expensive.', 'sale', '2025-05-18 19:32:32', 1),
(34, 29, 11, NULL, 173.00, 'like new', 'sold', 'I only sell on platform. I dont do meeting for the book.', 'sale', '2025-05-18 19:34:09', 1),
(35, 30, 11, NULL, 340.00, 'like new', 'active', 'Its a great book. Message me for more detailed info.', 'sale', '2025-05-18 19:35:59', 1);

CREATE TABLE `listing_image` (
  `image_id` int(11) NOT NULL,
  `listing_id` int(11) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

INSERT INTO `listing_image` (`image_id`, `listing_id`, `image_url`) VALUES
(1, 28, '/uploads/682a080684dd1.jpg'),
(5, 30, '/uploads/682a0ab69fa0d.jpg'),
(6, 31, '/uploads/682a0b08dfc16.jpg'),
(7, 32, '/uploads/682a0b5ba39a5.jpg'),
(8, 33, '/uploads/682a0ba0c30e6.jpg'),
(9, 34, '/uploads/682a0c014a1ef.jpg'),
(10, 35, '/uploads/682a0c6f70e87.jpg'),
(11, 29, '/uploads/682a0892c5a35.jpg');

CREATE TABLE `message` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `text` text NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

INSERT INTO `message` (`message_id`, `sender_id`, `receiver_id`, `text`, `timestamp`, `is_read`) VALUES
(1, 12, 11, 'Hi, I\'m interested in the Silver in the Bone book. Is it still available?', '2025-05-18 19:40:00', 1),
(2, 11, 12, 'Yes, it\'s still available! It\'s a signed copy in perfect condition.', '2025-05-18 19:42:00', 1),
(3, 12, 11, 'That\'s great! Could you tell me more about the signature? When was it signed?', '2025-05-18 19:45:00', 1),
(4, 11, 12, 'The author signed it during a book fair last month.', '2025-05-18 19:47:00', 1),
(25, 10, 11, 'Reminder: Please make sure to update the status of your listings once they\'re sold.', '2025-05-18 22:00:00', 0),
(26, 10, 12, 'Reminder: Always complete transactions through our platform for security.', '2025-05-18 22:00:00', 0);

CREATE TABLE `rating` (
  `rating_id` int(11) NOT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `reviewed_user_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `rating_date` date DEFAULT NULL,
  `admin_approved` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

CREATE TABLE `transaction` (
  `transaction_id` int(11) NOT NULL,
  `buyer_id` int(11) DEFAULT NULL,
  `listing_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `transaction_type` varchar(20) DEFAULT NULL CHECK (`transaction_type` in ('purchase','exchange')),
  `delivery_address` text DEFAULT NULL,
  `tracking_code` varchar(100) DEFAULT NULL,
  `courier_name` varchar(100) DEFAULT NULL,
  `transaction_status` varchar(20) DEFAULT NULL CHECK (`transaction_status` in ('paid','shipped','delivered','cancelled','completed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

INSERT INTO `transaction` (`transaction_id`, `buyer_id`, `listing_id`, `amount`, `transaction_date`, `transaction_type`, `delivery_address`, `tracking_code`, `courier_name`, `transaction_status`) VALUES
(1, 12, 34, 173.00, '2025-05-18 19:52:02', 'purchase', 'Home Apartment No:323 Famagusta/TRNC', '123456789', 'CampusExpress', 'delivered'),
(2, 12, 31, 180.00, '2025-05-18 19:52:42', 'purchase', 'Home Apartment No:23 Famagusta/TRNC', NULL, NULL, 'paid');

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `reg_date` date NOT NULL,
  `pass_hash` varchar(255) NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `is_blocked` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

INSERT INTO `user` (`user_id`, `phone`, `name`, `email`, `reg_date`, `pass_hash`, `is_admin`, `is_blocked`) VALUES
(10, '123456789', 'Admin Demo Account', 'demoadmin@example.com', '2025-05-18', '$2y$10$oZfXN/DqclXGetI4MbH01.Z7e9I3mZ2esEBc4K95aBsQxCGqXE9kS', 1, 0),
(11, '123456789', 'Demo Seller Account', 'demoseller@example.com', '2025-05-18', '$2y$10$uj0VMiCZ53eJEjUPP.PbcO19XPMr7LUiTBM25D1mULtw3EzlzL1.6', 0, 0),
(12, '123456789', 'Demo Buyer Account', 'demobuyer@example.com', '2025-05-18', '$2y$10$x560yxZOaPJL3ueqqyhuIuOu6qp/IG8unW/OJPIhytW06V6RAKEdm', 0, 0);


ALTER TABLE `book`
  ADD PRIMARY KEY (`book_id`);

ALTER TABLE `book_category`
  ADD PRIMARY KEY (`book_id`,`cat_id`),
  ADD KEY `cat_id` (`cat_id`);

ALTER TABLE `category`
  ADD PRIMARY KEY (`cat_id`);

ALTER TABLE `favorite`
  ADD PRIMARY KEY (`user_id`,`listing_id`),
  ADD KEY `listing_id` (`listing_id`);

ALTER TABLE `listing`
  ADD PRIMARY KEY (`listing_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

ALTER TABLE `listing_image`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `listing_id` (`listing_id`);

ALTER TABLE `message`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

ALTER TABLE `rating`
  ADD PRIMARY KEY (`rating_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `reviewed_user_id` (`reviewed_user_id`);

ALTER TABLE `transaction`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `listing_id` (`listing_id`);

ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);


ALTER TABLE `book`
  MODIFY `book_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

ALTER TABLE `category`
  MODIFY `cat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

ALTER TABLE `listing`
  MODIFY `listing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

ALTER TABLE `listing_image`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

ALTER TABLE `message`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

ALTER TABLE `rating`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `transaction`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;


ALTER TABLE `book_category`
  ADD CONSTRAINT `book_category_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `book` (`book_id`),
  ADD CONSTRAINT `book_category_ibfk_2` FOREIGN KEY (`cat_id`) REFERENCES `category` (`cat_id`);

ALTER TABLE `favorite`
  ADD CONSTRAINT `favorite_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `favorite_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `listing` (`listing_id`);

ALTER TABLE `listing`
  ADD CONSTRAINT `listing_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `listing_ibfk_3` FOREIGN KEY (`book_id`) REFERENCES `book` (`book_id`);

ALTER TABLE `listing_image`
  ADD CONSTRAINT `listing_image_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listing` (`listing_id`);

ALTER TABLE `message`
  ADD CONSTRAINT `message_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `message_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `user` (`user_id`);

ALTER TABLE `rating`
  ADD CONSTRAINT `rating_ibfk_1` FOREIGN KEY (`reviewer_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `rating_ibfk_2` FOREIGN KEY (`reviewed_user_id`) REFERENCES `user` (`user_id`);

ALTER TABLE `transaction`
  ADD CONSTRAINT `transaction_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `transaction_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `listing` (`listing_id`);
COMMIT;
