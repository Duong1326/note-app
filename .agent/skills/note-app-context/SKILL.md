---
name: note-app-context
description: Context, architecture, and feature overview of the Note App project. Use this skill to understand the business logic, models, and application setup before modifying the codebase.
---

# Note App Context

This project is a web-based Note-taking application built with Laravel and Docker.

## Tech Stack
- **Backend:** Laravel (PHP 8.3)
- **Frontend:** Laravel Blade templates (HTML/CSS/JS)
- **Database:** MariaDB (via Docker)
- **File Storage:** Cloudinary (for note attachments)
- **Infrastructure:** Docker Compose (Nginx, App, MariaDB)

## Core Models
- `User`: Standard authentication model.
- `Note`: The primary entity. Notes have content, can be pinned, and can be locked with a password.
- `Label`: For categorizing notes.
- `Attachment`: For storing images/files associated with a Note (uploaded to Cloudinary).
- `NoteShare`: For managing shared access of notes between users.
- `PasswordResetToken`: For handling OTP-based password resets.

## Key Features & Controllers
- **Authentication (`AuthController`, `ForgotPasswordController`, `ResetPasswordController`)**
  - Registration, Login, and Logout.
  - Password reset workflow uses an OTP via email instead of standard token links.
- **Dashboard (`DashboardController`)**
  - Main view for users to see their notes and filter them by `Label`.
- **Note Management (`NoteController`, `NoteLockController`, `AttachmentController`)**
  - Standard CRUD.
  - **Pinning:** Pin/unpin notes to top.
  - **Locking:** Notes can be locked with a specific password (`NoteLockController`). The user must verify the password to unlock the note before editing/viewing sensitive content.
  - **Attachments:** Supports uploading image attachments using Cloudinary.
- **Note Sharing (`NoteShareController`)**
  - Users can share notes with other registered users. Shared notes are displayed in a separate view.
- **Label Management (`LabelController`)**
  - Users can create, edit, and delete labels.
- **Profile (`ProfileController`)**
  - Users can update their profile information and avatar.

## Environment & Infrastructure
- The application is containerized. It uses `docker-compose.yml` to spin up 3 services: `app` (PHP application), `nginx` (web server), and `mysql` (MariaDB 11).
- Use `docker-compose up -d` to start the application locally.

## Guidelines for Development
- Ensure routes that modify locked notes use the appropriate middleware (`note.token`) or lock verification logic.
- Use Blade templates for new UI components, keeping consistency with existing `resources/views` layouts.
- When working with files/images, refer to `AttachmentController` to see how Cloudinary integration is handled.
- Stick to Laravel's Eloquent ORM and standard MVC practices.
