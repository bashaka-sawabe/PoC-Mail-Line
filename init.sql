CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    line_id VARCHAR(255) NOT NULL
);

INSERT INTO users (email, line_id) VALUES ('user1@example.com', 'line_user1');
INSERT INTO users (email, line_id) VALUES ('user2@example.com', 'line_user2');
