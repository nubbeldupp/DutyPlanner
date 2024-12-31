-- Create the database
CREATE DATABASE IF NOT EXISTS dutyplanner;
USE dutyplanner;

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
);

-- Insert default roles if they don't exist
INSERT IGNORE INTO roles (role_name, description) VALUES 
('admin', 'Full system access and control'),
('teamlead', 'Team management and shift approvals'),
('teammember', 'Basic shift creation and viewing');

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Teams table
CREATE TABLE teams (
    team_id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User roles table (for team membership and roles)
CREATE TABLE user_roles (
    user_id INT,
    team_id INT,
    role_type ENUM('admin', 'teamlead', 'teammember') NOT NULL,
    role_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, team_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE SET NULL
);

-- Map role_type to numeric role_id
UPDATE user_roles 
SET role_id = CASE 
    WHEN role_type = 'admin' THEN 1
    WHEN role_type = 'teamlead' THEN 2
    WHEN role_type = 'teammember' THEN 3
    ELSE NULL
END
WHERE role_id IS NULL;

-- Shifts table
CREATE TABLE shifts (
    shift_id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT,
    user_id INT,
    shift_type ENUM('regular', 'adhoc') NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Create indexes for better performance
CREATE INDEX idx_shifts_team_status ON shifts(team_id, status);
CREATE INDEX idx_shifts_user_status ON shifts(user_id, status);
CREATE INDEX idx_shifts_dates ON shifts(start_time, end_time);
CREATE INDEX idx_user_roles_team ON user_roles(team_id);
CREATE INDEX idx_user_roles_user ON user_roles(user_id);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password) VALUES 
('admin', 'admin@example.com', '$2y$10$8tPeEQY1o0jbS.HyMHUE8OQQrK.yi3F/oiFcGvz5h0HhHYKnzXAr.');

-- Insert default teams
INSERT INTO teams (team_name) VALUES 
('Hana'),
('Oracle'),
('Postgres'),
('SQLServer');

-- Assign admin role to default user for all teams
INSERT INTO user_roles (user_id, team_id, role_type, role_id)
SELECT 1, team_id, 'admin', 1
FROM teams;

-- Create views for common queries
CREATE VIEW v_active_shifts AS
SELECT s.*, t.team_name, u.username, u.email
FROM shifts s
JOIN teams t ON s.team_id = t.team_id
JOIN users u ON s.user_id = u.user_id
WHERE s.end_time >= CURRENT_TIMESTAMP
AND s.status = 'approved';

CREATE VIEW v_team_members AS
SELECT t.team_id, t.team_name, u.user_id, u.username, u.email, ur.role_type
FROM teams t
JOIN user_roles ur ON t.team_id = ur.team_id
JOIN users u ON ur.user_id = u.user_id;

-- Create stored procedure for shift overlap check
DELIMITER //
CREATE PROCEDURE check_shift_overlap(
    IN p_user_id INT,
    IN p_start_time DATETIME,
    IN p_end_time DATETIME
)
BEGIN
    SELECT COUNT(*) INTO @overlap_count
    FROM shifts
    WHERE user_id = p_user_id
    AND status = 'approved'
    AND (
        (start_time BETWEEN p_start_time AND p_end_time)
        OR (end_time BETWEEN p_start_time AND p_end_time)
        OR (start_time <= p_start_time AND end_time >= p_end_time)
    );
    
    SELECT @overlap_count > 0 AS has_overlap;
END //
DELIMITER ;

-- Create trigger to prevent overlapping approved shifts
DELIMITER //
CREATE TRIGGER before_shift_update
BEFORE UPDATE ON shifts
FOR EACH ROW
BEGIN
    IF NEW.status = 'approved' THEN
        CALL check_shift_overlap(NEW.user_id, NEW.start_time, NEW.end_time);
        IF @overlap_count > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Shift overlaps with existing approved shifts';
        END IF;
    END IF;
END //
DELIMITER ;

-- Create trigger to log shift status changes
CREATE TABLE shift_status_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT,
    old_status VARCHAR(20),
    new_status VARCHAR(20),
    changed_by INT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_id) REFERENCES shifts(shift_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL
);

DELIMITER //
CREATE TRIGGER after_shift_status_change
AFTER UPDATE ON shifts
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO shift_status_log (shift_id, old_status, new_status, changed_by)
        VALUES (NEW.shift_id, OLD.status, NEW.status, NEW.approved_by);
    END IF;
END //
DELIMITER ;