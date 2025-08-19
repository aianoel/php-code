-- Create chat_messages table if it doesn't exist
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id VARCHAR(50) NOT NULL,
    sender ENUM('user', 'ai') NOT NULL,
    message TEXT NOT NULL,
    timestamp DATETIME NOT NULL,
    INDEX (chat_id)
);
