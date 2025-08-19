-- Landing page content table
CREATE TABLE IF NOT EXISTS landing_page_content (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_name VARCHAR(100) NOT NULL,
    title VARCHAR(200) NOT NULL,
    subtitle VARCHAR(200),
    content TEXT,
    image_url VARCHAR(255),
    button_text VARCHAR(50),
    button_url VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Landing page announcements table
CREATE TABLE IF NOT EXISTS landing_announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message TEXT NOT NULL,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default landing page content
INSERT INTO landing_page_content (section_name, title, subtitle, content, display_order, is_active) VALUES
('hero', 'Welcome to EduManage School', 'Empowering Students for a Brighter Future', 'We provide quality education with modern teaching methods and a supportive learning environment.', 1, TRUE),
('about', 'About Our School', 'Excellence in Education Since 2000', 'EduManage School is dedicated to providing a comprehensive education that prepares students for success in college and beyond.', 2, TRUE),
('programs', 'Our Programs', 'Comprehensive Learning Paths', 'We offer a variety of academic programs designed to meet the diverse needs of our students.', 3, TRUE);

-- Insert sample announcements
INSERT INTO landing_announcements (message, display_order, is_active) VALUES
('Enrollment for the new academic year is now open! Apply today.', 1, TRUE),
('Parent-Teacher conference scheduled for next Friday. Please mark your calendars.', 2, TRUE),
('Congratulations to our Science Olympiad team for winning the regional championship!', 3, TRUE);
