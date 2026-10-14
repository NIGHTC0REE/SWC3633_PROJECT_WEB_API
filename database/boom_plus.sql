DROP DATABASE IF EXISTS boom_plus;
CREATE DATABASE boom_plus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE boom_plus;

CREATE TABLE users (
  user_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role          ENUM('admin','organiser','customer') NOT NULL DEFAULT 'customer',
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone         VARCHAR(20)  NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  reset_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64)     NOT NULL,
  expires_at DATETIME     NOT NULL,
  used_at    DATETIME     NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (reset_id),
  UNIQUE KEY uq_reset_token (token_hash),
  KEY idx_reset_user (user_id),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE venues (
  venue_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id      INT UNSIGNED NOT NULL,
  venue_name    VARCHAR(120) NOT NULL,
  address       VARCHAR(255) NOT NULL,
  city          VARCHAR(80)  NOT NULL,
  capacity      INT UNSIGNED NOT NULL,
  contact_phone VARCHAR(20)  NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (venue_id),
  KEY idx_venues_owner (owner_id),
  KEY idx_venues_city (city),
  CONSTRAINT fk_venues_owner FOREIGN KEY (owner_id) REFERENCES users (user_id),
  CONSTRAINT chk_venue_capacity CHECK (capacity > 0)
) ENGINE=InnoDB;

CREATE TABLE courts (
  court_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  venue_id    INT UNSIGNED NOT NULL,
  sport       ENUM('Pickleball','Futsal','Badminton') NOT NULL,
  court_name  VARCHAR(60)  NOT NULL,
  hourly_rate DECIMAL(8,2) NOT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (court_id),
  UNIQUE KEY uq_court_per_venue (venue_id, court_name),
  KEY idx_courts_sport (sport),
  CONSTRAINT fk_courts_venue FOREIGN KEY (venue_id) REFERENCES venues (venue_id) ON DELETE CASCADE,
  CONSTRAINT chk_court_rate CHECK (hourly_rate >= 0)
) ENGINE=InnoDB;

CREATE TABLE events (
  event_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organiser_id INT UNSIGNED NOT NULL,
  venue_id     INT UNSIGNED NOT NULL,
  sport        ENUM('Pickleball','Futsal','Badminton') NOT NULL,
  title        VARCHAR(150) NOT NULL,
  description  TEXT NULL,
  start_time   DATETIME NOT NULL,
  end_time     DATETIME NOT NULL,
  status       ENUM('draft','published','cancelled','completed') NOT NULL DEFAULT 'draft',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id),
  KEY idx_events_start (start_time),
  KEY idx_events_status (status),
  KEY idx_events_sport (sport),
  KEY idx_events_venue (venue_id, start_time),
  KEY idx_events_organiser (organiser_id),
  CONSTRAINT fk_events_organiser FOREIGN KEY (organiser_id) REFERENCES users (user_id),
  CONSTRAINT fk_events_venue     FOREIGN KEY (venue_id)     REFERENCES venues (venue_id),
  CONSTRAINT chk_event_time CHECK (end_time > start_time)
) ENGINE=InnoDB;

CREATE TABLE ticket_types (
  ticket_type_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id       INT UNSIGNED NOT NULL,
  type_name      VARCHAR(50)  NOT NULL,
  price          DECIMAL(8,2) NOT NULL,
  capacity       INT UNSIGNED NOT NULL,
  sold           INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (ticket_type_id),
  UNIQUE KEY uq_ticket_type (event_id, type_name),
  CONSTRAINT fk_ticket_event FOREIGN KEY (event_id) REFERENCES events (event_id) ON DELETE CASCADE,
  CONSTRAINT chk_ticket_price CHECK (price >= 0),
  CONSTRAINT chk_ticket_sold  CHECK (sold <= capacity)
) ENGINE=InnoDB;

CREATE TABLE bookings (
  booking_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_ref    CHAR(10)     NOT NULL,
  user_id        INT UNSIGNED NOT NULL,
  booking_type   ENUM('event_ticket','court') NOT NULL,
  ticket_type_id INT UNSIGNED NULL,
  court_id       INT UNSIGNED NULL,
  quantity       INT UNSIGNED NOT NULL DEFAULT 1,
  slot_start     DATETIME NULL,
  slot_end       DATETIME NULL,
  total_amount   DECIMAL(10,2) NOT NULL,
  status         ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
  qr_code        MEDIUMTEXT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (booking_id),
  UNIQUE KEY uq_booking_ref (booking_ref),
  KEY idx_bookings_user (user_id, created_at),
  KEY idx_bookings_status (status),
  KEY idx_bookings_ticket (ticket_type_id),
  KEY idx_bookings_court_slot (court_id, slot_start, slot_end),
  CONSTRAINT fk_booking_user   FOREIGN KEY (user_id)        REFERENCES users (user_id),
  CONSTRAINT fk_booking_ticket FOREIGN KEY (ticket_type_id) REFERENCES ticket_types (ticket_type_id),
  CONSTRAINT fk_booking_court  FOREIGN KEY (court_id)       REFERENCES courts (court_id),
  CONSTRAINT chk_booking_qty   CHECK (quantity > 0)
) ENGINE=InnoDB;

CREATE TABLE payments (
  payment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NOT NULL,
  amount     DECIMAL(10,2) NOT NULL,
  method     ENUM('card','fpx','ewallet','cash') NOT NULL,
  status     ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  paid_at    DATETIME NULL,
  PRIMARY KEY (payment_id),
  UNIQUE KEY uq_payment_booking (booking_id),
  CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings (booking_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  log_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(60)  NOT NULL,
  detail     VARCHAR(255) NULL,
  ip_address VARCHAR(45)  NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_action (action),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE request_logs (
  request_id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  method      VARCHAR(10)  NOT NULL,
  path        VARCHAR(255) NOT NULL,
  status_code SMALLINT UNSIGNED NOT NULL,
  duration_ms INT UNSIGNED NOT NULL,
  ip_address  VARCHAR(45)  NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (request_id),
  KEY idx_request_created (created_at),
  KEY idx_request_status (status_code)
) ENGINE=InnoDB;

CREATE TABLE rate_limits (
  bucket       VARCHAR(80)  NOT NULL,
  window_start INT UNSIGNED NOT NULL,
  hits         INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (bucket, window_start)
) ENGINE=InnoDB;

INSERT INTO users (user_id, role, full_name, email, password_hash, phone, is_active) VALUES
 (1,'admin',    'Hafiq Admin',    'admin@boom.test',     '$2y$10$Yn7TG/1gFvConvtYeMRoXeNFFrUHXLUHs6if.YHYGP6ivwGAa0Iou','0111111111',1),
 (2,'organiser','Akma Organiser', 'organiser@boom.test', '$2y$10$Yn7TG/1gFvConvtYeMRoXeNFFrUHXLUHs6if.YHYGP6ivwGAa0Iou','0122222222',1),
 (3,'organiser','Putera Sports',  'putera@boom.test',    '$2y$10$Yn7TG/1gFvConvtYeMRoXeNFFrUHXLUHs6if.YHYGP6ivwGAa0Iou','0133333333',1),
 (4,'customer', 'Arep Customer',  'customer@boom.test',  '$2y$10$Yn7TG/1gFvConvtYeMRoXeNFFrUHXLUHs6if.YHYGP6ivwGAa0Iou','0144444444',1),
 (5,'customer', 'Siti Aminah',    'siti@boom.test',      '$2y$10$Yn7TG/1gFvConvtYeMRoXeNFFrUHXLUHs6if.YHYGP6ivwGAa0Iou','0155555555',1),
 (6,'customer', 'Lim Wei',        'lim@boom.test',       '$2y$10$Yn7TG/1gFvConvtYeMRoXeNFFrUHXLUHs6if.YHYGP6ivwGAa0Iou','0166666666',1),
 (7,'customer', 'Nurul Huda',     'nurul@boom.test',     '$2y$10$Yn7TG/1gFvConvtYeMRoXeNFFrUHXLUHs6if.YHYGP6ivwGAa0Iou','0177777777',0);

INSERT INTO password_resets (reset_id, user_id, token_hash, expires_at, used_at) VALUES
 (1,4,SHA2('demo-token-1',256),DATE_ADD(NOW(),INTERVAL 1 HOUR),NULL),
 (2,5,SHA2('demo-token-2',256),DATE_ADD(NOW(),INTERVAL 1 HOUR),NULL),
 (3,6,SHA2('demo-token-3',256),DATE_SUB(NOW(),INTERVAL 1 DAY),NULL),
 (4,3,SHA2('demo-token-4',256),DATE_SUB(NOW(),INTERVAL 2 DAY),DATE_SUB(NOW(),INTERVAL 2 DAY)),
 (5,2,SHA2('demo-token-5',256),DATE_ADD(NOW(),INTERVAL 30 MINUTE),NULL);

INSERT INTO venues (venue_id, owner_id, venue_name, address, city, capacity, contact_phone, is_active) VALUES
 (1,2,'BOOM Arena Cheras',    '12 Jalan Cheras Sport', 'Kuala Lumpur',500,'03-11111111',1),
 (2,2,'Pickle Point Bangsar', '88 Jalan Telawi',       'Kuala Lumpur',200,'03-22222222',1),
 (3,3,'Shuttle Hub Shah Alam','5 Persiaran Sukan',     'Shah Alam',   300,'03-33333333',1),
 (4,3,'Futsal Zone Subang',   '21 Jalan SS15',         'Subang Jaya', 250,'03-44444444',1),
 (5,2,'Raja Court Penang',    '9 Lebuh Sukan',         'George Town', 400,'04-55555555',1);

INSERT INTO courts (court_id, venue_id, sport, court_name, hourly_rate, is_active) VALUES
 (1,1,'Futsal',    'Futsal Court A',     120.00,1),
 (2,1,'Futsal',    'Futsal Court B',     110.00,1),
 (3,2,'Pickleball','Pickleball Court 1',  45.00,1),
 (4,2,'Pickleball','Pickleball Court 2',  45.00,1),
 (5,3,'Badminton', 'Badminton Court 1',   30.00,1),
 (6,3,'Badminton', 'Badminton Court 2',   30.00,1),
 (7,4,'Futsal',    'Futsal Court C',     105.00,1),
 (8,5,'Badminton', 'Badminton Court 3',   28.00,1);

INSERT INTO events (event_id, organiser_id, venue_id, sport, title, description, start_time, end_time, status) VALUES
 (1,2,1,'Futsal',    'BOOM Futsal Cup',           '8-team knockout futsal tournament',      TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 14 DAY),'09:00:00'),TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 14 DAY),'18:00:00'),'published'),
 (2,2,2,'Pickleball','Pickleball Open Day',       'Beginner-friendly pickleball clinic',    TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 21 DAY),'10:00:00'),TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 21 DAY),'15:00:00'),'published'),
 (3,3,3,'Badminton', 'Smash Badminton Series',    'Doubles badminton league night',         TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 30 DAY),'18:00:00'),TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 30 DAY),'22:00:00'),'published'),
 (4,3,4,'Futsal',    'Subang Futsal League',      'Weekly league fixtures',                 TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 35 DAY),'19:00:00'),TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 35 DAY),'22:00:00'),'draft'),
 (5,2,5,'Badminton', 'Penang Badminton Masters',  'Open singles championship',              TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 60 DAY),'09:00:00'),TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 60 DAY),'17:00:00'),'published'),
 (6,2,1,'Futsal',    'Cheras Futsal Kids Clinic', 'Skills clinic for ages 8 to 12',         TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 45 DAY),'09:00:00'),TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 45 DAY),'12:00:00'),'published'),
 (7,3,2,'Pickleball','Pickle Mixed Doubles',      'Social mixed doubles round robin',       TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 28 DAY),'14:00:00'),TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 28 DAY),'18:00:00'),'published'),
 (8,2,1,'Futsal',    'Legends Charity Match',     'Charity match with former internationals',TIMESTAMP(DATE_SUB(CURDATE(),INTERVAL 20 DAY),'16:00:00'),TIMESTAMP(DATE_SUB(CURDATE(),INTERVAL 20 DAY),'19:00:00'),'completed');

INSERT INTO ticket_types (ticket_type_id, event_id, type_name, price, capacity, sold) VALUES
 (1, 1,'General',25.00,400,2),
 (2, 1,'VIP',    60.00, 50,0),
 (3, 2,'General',15.00,150,1),
 (4, 3,'VIP',    40.00, 50,1),
 (5, 3,'General',20.00,200,2),
 (6, 4,'General',20.00,200,0),
 (7, 5,'General',18.00,300,0),
 (8, 6,'General',10.00, 60,0),
 (9, 7,'General',12.00, 80,0),
 (10,8,'General',15.00,300,3);

INSERT INTO bookings (booking_id, booking_ref, user_id, booking_type, ticket_type_id, court_id, quantity, slot_start, slot_end, total_amount, status) VALUES
 (1,'BM00000001',4,'event_ticket',1,   NULL,2,NULL,NULL,50.00,'confirmed'),
 (2,'BM00000002',5,'event_ticket',3,   NULL,1,NULL,NULL,15.00,'confirmed'),
 (3,'BM00000003',6,'event_ticket',4,   NULL,1,NULL,NULL,40.00,'pending'),
 (4,'BM00000004',4,'court',      NULL, 5,   1,TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 3 DAY),'20:00:00'),TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 3 DAY),'21:00:00'),30.00,'confirmed'),
 (5,'BM00000005',5,'event_ticket',7,   NULL,1,NULL,NULL,18.00,'cancelled'),
 (6,'BM00000006',4,'event_ticket',5,   NULL,2,NULL,NULL,40.00,'confirmed'),
 (7,'BM00000007',6,'event_ticket',10,  NULL,3,NULL,NULL,45.00,'completed'),
 (8,'BM00000008',5,'court',      NULL, 3,   1,TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 5 DAY),'19:00:00'),TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 5 DAY),'21:00:00'),90.00,'confirmed');

INSERT INTO payments (payment_id, booking_id, amount, method, status, paid_at) VALUES
 (1,1,50.00,'card',   'paid',    NOW()),
 (2,2,15.00,'fpx',    'paid',    NOW()),
 (3,3,40.00,'cash',   'pending', NULL),
 (4,4,30.00,'ewallet','paid',    NOW()),
 (5,5,18.00,'card',   'refunded',NOW()),
 (6,6,40.00,'fpx',    'paid',    NOW()),
 (7,7,45.00,'card',   'paid',    DATE_SUB(NOW(),INTERVAL 21 DAY)),
 (8,8,90.00,'card',   'paid',    NOW());

INSERT INTO audit_logs (user_id, action, detail, ip_address) VALUES
 (1,'LOGIN','Admin login','127.0.0.1'),
 (2,'EVENT_CREATE','Created BOOM Futsal Cup','127.0.0.1'),
 (4,'REGISTER','New customer registered','127.0.0.1'),
 (4,'BOOKING_CREATE','Booking BM00000001','127.0.0.1'),
 (5,'BOOKING_CANCEL','Booking BM00000005','127.0.0.1');

INSERT INTO request_logs (user_id, method, path, status_code, duration_ms, ip_address) VALUES
 (NULL,'GET', '/events',          200, 12,'127.0.0.1'),
 (NULL,'POST','/auth/login',      401, 85,'127.0.0.1'),
 (4,   'POST','/bookings',        201, 48,'127.0.0.1'),
 (2,   'PUT', '/events/1',        200, 21,'127.0.0.1'),
 (NULL,'GET', '/unknown',         404,  3,'127.0.0.1');

INSERT INTO rate_limits (bucket, window_start, hits) VALUES
 ('ip:127.0.0.1',        1700000000, 12),
 ('ip:127.0.0.2',        1700000000,  4),
 ('login:127.0.0.1',     1700000000,  3),
 ('login:127.0.0.2',     1700000000,  1),
 ('register:127.0.0.1',  1700000000,  2);
