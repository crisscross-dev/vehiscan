-- Migration 010: Add draft_payload column to profile_update_requests
-- Stores structured JSON profile changes for admin review and application

ALTER TABLE `profile_update_requests`
ADD COLUMN `draft_payload` JSON NULL DEFAULT NULL AFTER `request_text`;
