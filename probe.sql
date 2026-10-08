
DROP DATABASE IF EXISTS mk_fk_probe;
CREATE DATABASE mk_fk_probe;
USE mk_fk_probe;
CREATE TABLE events (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, status VARCHAR(20));
CREATE TABLE event_teacher_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  CONSTRAINT fk_a_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);
CREATE TABLE event_participants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  active_teacher_assignment_id BIGINT UNSIGNED NULL,
  CONSTRAINT fk_p_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_p_assign FOREIGN KEY (active_teacher_assignment_id) REFERENCES event_teacher_assignments(id) ON DELETE RESTRICT
);
CREATE TABLE event_teacher_reassignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  event_participant_id BIGINT UNSIGNED NOT NULL,
  CONSTRAINT fk_r_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_r_part FOREIGN KEY (event_participant_id) REFERENCES event_participants(id) ON DELETE CASCADE
);
CREATE TABLE invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NULL,
  CONSTRAINT fk_i_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE RESTRICT
);
INSERT INTO events (status) VALUES ('CANCELLED');
INSERT INTO event_teacher_assignments (event_id) VALUES (1);
INSERT INTO event_participants (event_id, active_teacher_assignment_id) VALUES (1, 1);
INSERT INTO event_teacher_reassignments (event_id, event_participant_id) VALUES (1, 1);
INSERT INTO invoices (event_id) VALUES (1);
