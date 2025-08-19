-- News table for school news articles
CREATE TABLE IF NOT EXISTS news (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    excerpt TEXT,
    content TEXT NOT NULL,
    date DATE NOT NULL,
    image_url VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Events table for school events
CREATE TABLE IF NOT EXISTS events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    date DATE NOT NULL,
    time TIME,
    location VARCHAR(200),
    image_url VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample news
INSERT INTO news (title, excerpt, content, date, display_order, is_active) VALUES
('School Year 2024-2025 Begins', 'Welcome back to all students for the new school year!', 'We are excited to welcome all students back to campus for the 2024-2025 school year. Please check the academic calendar for important dates and events.', CURRENT_DATE(), 1, TRUE),
('New STEM Laboratory Opening', 'State-of-the-art STEM lab now available for students', 'We are proud to announce the opening of our new STEM laboratory equipped with the latest technology and resources to enhance learning experiences.', CURRENT_DATE(), 2, TRUE);

-- Insert sample events
INSERT INTO events (title, description, date, time, location, display_order, is_active) VALUES
('Parent-Teacher Conference', 'Meet with teachers to discuss student progress', DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY), '13:00:00', 'Main Auditorium', 1, TRUE),
('Science Fair', 'Annual science fair showcasing student projects', DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY), '09:00:00', 'School Gymnasium', 2, TRUE);
