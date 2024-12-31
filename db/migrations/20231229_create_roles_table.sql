-- Roles Migration Script

-- Ensure roles table exists
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

-- User Roles Migration Script

-- Add role_id column if it doesn't exist
ALTER TABLE user_roles 
ADD COLUMN IF NOT EXISTS role_id INT;

-- Map role_type to numeric role_id
UPDATE user_roles 
SET role_id = CASE 
    WHEN role_type = 'admin' THEN 1
    WHEN role_type = 'teamlead' THEN 2
    WHEN role_type = 'teammember' THEN 3
    ELSE NULL
END
WHERE role_id IS NULL;
