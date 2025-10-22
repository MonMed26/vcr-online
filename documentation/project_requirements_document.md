# Project Requirements Document: vcr-online

## 1. Project Overview

vcr-online is a web-based platform that brings the classic Video Cassette Recorder experience to the internet. It combines a realistic VCR interface emulation, remote control of physical VCR hardware, and a comprehensive content management system for digitized recordings. Users can play, pause, rewind, fast-forward, or record virtual tapes in their browser, connect to real-world VCR devices for remote operation, and build a searchable archive of video assets.

The platform is being built to simplify tape preservation, remote access, and online sharing of analog video content. Key objectives include delivering an authentic VCR interface in-browser, enabling secure remote control of connected devices, and providing an intuitive library for tagging, transcoding, and streaming video files. Success will be measured by low-latency playback (< 2 seconds), 99% uptime for streaming services, and user satisfaction scores above 4 out of 5.

## 2. In-Scope vs. Out-of-Scope

**In-Scope (Version 1.0)**
- Browser-based VCR emulation with core controls: Play, Pause, Rewind, Fast-forward, Record.
- Secure user registration, login, and role-based access control.
- Remote pairing and control of a single physical VCR unit over USB or network adapter.
- Centralized tape library with metadata fields (title, date, tags) and search/filter capabilities.
- Transcoding pipeline to convert incoming analog capture to MP4 or WebM.
- Real-time streaming via HLS or WebRTC with adaptive bitrate.
- RESTful API endpoints for all major operations (device pairing, tape CRUD, commands, streaming).
- Basic dashboard UI showing device status, library overview, and live stream preview.

**Out-of-Scope (Planned for Later Phases)**
- Mobile-native applications (iOS/Android).
- Multi-device synchronization or clustering of physical VCRs.
- Advanced video editing (cuts, overlays) or AI-based enhancement.
- Social sharing, comments, or community features.
- Subscription or billing integration.
- Offline playback or service worker caching.

## 3. User Flow

A new user navigates to vcr-online.com, clicks "Sign Up," and provides an email and password. After email verification, they log in to land on the Dashboard, which displays any paired VCR devices, recent recordings, and the left sidebar for navigation (Devices, Library, Streaming, Account). The main area shows a quick snapshot: device online/offline status, latest transcoding jobs, and a "Start Emulation" button.

To emulate a tape, the user clicks "Devices," selects a paired VCR, and presses "Emulate VCR." The screen loads a realistic VCR front panel with buttons and a tape slot graphic. Meanwhile, the user can head to "Library" to upload existing MP4/WebM files or view entries created after recording from a real device. Selecting any tape in the library opens a video player with playback controls for on-demand viewing or triggers a live stream from the hardware if available.

## 4. Core Features

- **VCR Emulation Engine:** JavaScript-driven state machine for tape position, play state, and control animations.
- **User Authentication & Roles:** Email/password signup, password reset, and administrator vs. standard user roles.
- **Device Pairing & Control:** QR-style pairing flow, secure token exchange, and real-time command routing to USB/networked VCRs.
- **Tape Library & Metadata:** CRUD operations on tape entries, tagging, categories, and full-text search.
- **Transcoding Pipeline:** Automated processing using FFmpeg to convert analog captures to MP4/WebM.
- **Real-Time Streaming:** HLS for broad compatibility, WebRTC for low latency, adaptive bitrate support.
- **RESTful API:** OpenAPI/Swagger specification covering all endpoints for integration and automation.
- **Dashboard & UI Components:** React-based components for sidebar navigation, status panels, library grids, and video players.

## 5. Tech Stack & Tools

- **Frontend:**
  - Framework: React + Next.js for server-side rendering
  - Styling: Tailwind CSS
  - Video Playback: video.js for HLS, simple WebRTC wrappers
  - IDE/Plugins: VS Code with Prettier, ESLint, React DevTools

- **Backend:**
  - Runtime: Node.js (v18+)
  - Framework: Express.js or NestJS
  - Transcoding: FFmpeg CLI invoked via fluent-ffmpeg (Node)
  - Database: PostgreSQL for metadata, Redis for session caching
  - Streaming: Node Media Server or custom HLS packager
  - IDE/Plugins: WebStorm or VS Code with Docker, Node.js integrations

- **Infrastructure:**
  - Containerization: Docker and Docker Compose
  - Cloud: AWS (EC2, S3 for storage, CloudFront for CDN), or GCP equivalent
  - CI/CD: GitHub Actions, Docker Hub automated builds

- **API Documentation:** OpenAPI (Swagger)

## 6. Non-Functional Requirements

- Performance: Page load under 1 second (first contentful paint), streaming startup < 2 seconds.
- Scalability: Support up to 100 concurrent streams in V1; horizontal scaling via Docker Swarm or Kubernetes later.
- Security: TLS everywhere, secure storage of device tokens, OWASP Top 10 compliance, GDPR-friendly data handling.
- Reliability: 99% uptime SLA for streaming and device control endpoints.
- Usability: Fully responsive layout, keyboard accessibility for VCR controls, WCAG 2.1 AA compliance.

## 7. Constraints & Assumptions

- Physical VCR control requires a USB capture adapter or network interface and proprietary driver software on a local gateway service.
- Users supply their own VCR hardware and capture cables; vcr-online does not include hardware.
- Low-latency streaming will vary by user network; expect 1–3 second delays on average.
- PostgreSQL and Redis must be available as managed services or self-hosted instances.

## 8. Known Issues & Potential Pitfalls

- **Browser Compatibility:** WebRTC support differs across browsers—provide HLS fallback.
- **API Rate Limits:** HLS chunk generation can be CPU-intensive; plan for queuing and back-pressure.
- **Hardware Integration:** USB drivers may require native modules—limit initial support to popular capture devices.
- **Transcoding Load:** FFmpeg jobs could overwhelm a single server—schedule jobs and scale workers.
- **Network Firewalls:** Device control over corporate networks may be blocked; suggest port-forwarding guides.

---

This document provides a clear, unambiguous guide for the AI model to generate technical designs, frontend guidelines, backend structure, and more. All core features, flows, and constraints are outlined to avoid guesswork in future phases.