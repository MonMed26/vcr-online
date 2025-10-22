# Backend Structure Document for vcr-online

## 1. Backend Architecture

**Overall Design**
- The backend is organized as a set of loosely coupled services, each handling a specific domain (emulation logic, hardware integration, streaming, user management).
- We follow a microservices-inspired approach, packaged with Docker and orchestrated via Kubernetes (EKS on AWS).
- Each service uses a common framework (Node.js with Express and TypeScript) and follows the Model-View-Controller (MVC) pattern internally.

**Scalability**
- Services run in containers that can be scaled horizontally by increasing pod counts.
- Stateless services (API, emulation) can be replicated behind a load balancer.
- Stateful components (databases, caching) are managed via cloud services (RDS, ElastiCache) with built-in high-availability.

**Maintainability**
- Clear service boundaries keep codebases small and focused.
- Shared libraries for logging, error handling, and authentication are published as npm packages.
- Git-based CI/CD pipelines run tests, linting, and automatic deployments for each service.

**Performance**
- Critical paths (video streaming, emulation state updates) are handled by specialized services.
- Caching via Redis reduces repeated database lookups for user sessions and tape metadata.
- A media server (e.g., Wowza or Janus) offloads real-time streaming, converting analog inputs into WebRTC/HLS streams.

## 2. Database Management

**Database Technologies**
- Relational (SQL): PostgreSQL (AWS RDS) for structured data—users, devices, tapes, metadata, and logs.
- In-memory store: Redis (AWS ElastiCache) for session caching, rate limiting, and temporary emulation state.

**Data Organization**
- PostgreSQL tables are normalized to avoid duplication. Key tables include Users, Tapes, Devices, Sessions, and TapeHistory.
- Redis stores ephemeral data: active WebRTC sessions, emulation tape head positions, and brief configuration flags.

**Data Practices**
- Automated daily backups of RDS snapshots.
- Point-in-time recovery enabled for up to 7 days.
- Regular vacuuming and indexing on PostgreSQL to maintain query performance.
- Encryption at rest (AES-256) and in transit (TLS) for both PostgreSQL and Redis.

## 3. Database Schema

### Human-Readable Schema Overview
- **Users**: stores account details and security settings.
- **Devices**: tracks physical VCR devices linked to user accounts.
- **Tapes**: catalog of video assets, including metadata and storage locations.
- **Sessions**: records of streaming or emulation sessions.
- **TapeHistory**: audit log of all actions performed on each tape (edits, transcoding events).

### PostgreSQL Schema (SQL)
```sql
CREATE TABLE Users (
  id SERIAL PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'user',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE Devices (
  id SERIAL PRIMARY KEY,
  user_id INTEGER REFERENCES Users(id) ON DELETE CASCADE,
  name VARCHAR(100) NOT NULL,
  ip_address VARCHAR(45),
  last_seen TIMESTAMP WITH TIME ZONE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE Tapes (
  id SERIAL PRIMARY KEY,
  user_id INTEGER REFERENCES Users(id) ON DELETE SET NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  recorded_at DATE,
  storage_uri TEXT NOT NULL,   -- e.g., S3 path or local path
  format VARCHAR(50) NOT NULL, -- MP4, WebM, etc.
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE Sessions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id INTEGER REFERENCES Users(id) ON DELETE CASCADE,
  tape_id INTEGER REFERENCES Tapes(id) ON DELETE SET NULL,
  device_id INTEGER REFERENCES Devices(id) ON DELETE SET NULL,
  type VARCHAR(50) NOT NULL,   -- 'emulation' or 'streaming'
  status VARCHAR(50) NOT NULL,
  started_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  ended_at TIMESTAMP WITH TIME ZONE
);

CREATE TABLE TapeHistory (
  id SERIAL PRIMARY KEY,
  tape_id INTEGER REFERENCES Tapes(id) ON DELETE CASCADE,
  action VARCHAR(100) NOT NULL,
  performed_by INTEGER REFERENCES Users(id) ON DELETE SET NULL,
  performed_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  details JSONB
);
```

## 4. API Design and Endpoints

**Approach**
- RESTful API with versioning (e.g., `/api/v1/...`).
- JSON request/response format.
- Controllers mapped to resource routes, using Express routers.

**Key Endpoints**
- **Authentication**
  - POST `/api/v1/auth/register` – create a new user account.
  - POST `/api/v1/auth/login` – obtain JWT token.
  - POST `/api/v1/auth/refresh` – refresh expired tokens.

- **Users & Profiles**
  - GET `/api/v1/users/me` – retrieve current user profile.
  - PATCH `/api/v1/users/me` – update profile settings.

- **Devices**
  - GET `/api/v1/devices` – list linked VCR devices.
  - POST `/api/v1/devices` – register a new device.
  - DELETE `/api/v1/devices/:id` – remove a device.

- **Tapes & Archives**
  - GET `/api/v1/tapes` – list or search tapes (filters by title, date).
  - POST `/api/v1/tapes` – catalog a new tape (with metadata).
  - GET `/api/v1/tapes/:id` – retrieve tape details.
  - PATCH `/api/v1/tapes/:id` – update tape metadata.
  - DELETE `/api/v1/tapes/:id` – delete a tape entry.

- **Sessions**
  - POST `/api/v1/sessions` – start an emulation or streaming session.
  - GET `/api/v1/sessions/:id` – check session status.
  - POST `/api/v1/sessions/:id/command` – send VCR commands (play, pause, rewind).
  - DELETE `/api/v1/sessions/:id` – end a session.

- **Streaming**
  - GET `/api/v1/streams/:sessionId/offer` – negotiate WebRTC offer.
  - POST `/api/v1/streams/:sessionId/answer` – accept WebRTC answer.
  - GET `/api/v1/hls/:tapeId/playlist.m3u8` – HLS streaming playlist.

## 5. Hosting Solutions

**Cloud Provider**
- Amazon Web Services (AWS) for most components.

**Core Services**
- EKS (Elastic Kubernetes Service) for container orchestration.
- RDS for PostgreSQL with Multi-AZ deployment.
- ElastiCache for Redis clustering.
- S3 for storing transcoded video files and snapshots.
- CloudFront as CDN for HLS segments and static assets.

**Benefits**
- High reliability and automatic failover.
- Scalability: auto-scaling groups and on-demand resource provisioning.
- Cost efficiency: pay-as-you-go, reserved instances for database.

## 6. Infrastructure Components

- **Load Balancer:** AWS Application Load Balancer distributes traffic to API and streaming service pods.
- **Caching:** Redis for quick lookups of sessions and tape metadata.
- **CDN:** CloudFront caches video chunks and static files close to users.
- **Media Servers:** Dedicated pods running a media server (e.g., Janus) to handle WebRTC signaling and HLS packaging.
- **Message Broker:** RabbitMQ or AWS SQS for event-driven tasks (e.g., transcoding pipeline triggers).

## 7. Security Measures

- **Authentication:** JWT tokens with short TTL and refresh tokens.
- **Authorization:** Role-based access control (RBAC) enforced in middleware.
- **Encryption:** TLS for all in-transit data; AES-256 at rest in databases and S3.
- **Network Security:** Private subnets for databases, security groups restricting ports.
- **API Protection:** Rate limiting, input validation, JSON schema checks to prevent injection.
- **Audit Logging:** All critical actions (logins, tape edits, device registrations) recorded in TapeHistory or CloudTrail.

## 8. Monitoring and Maintenance

- **Logging:** Centralized logs shipped to ELK stack (Elasticsearch, Logstash, Kibana).
- **Metrics & Alerts:** Prometheus and Grafana monitor pod health, CPU/memory, latency, error rates; alerts sent via SNS or PagerDuty.
- **Uptime Checks:** Synthetic transactions run periodically to verify key endpoints are responsive.
- **CI/CD:** GitHub Actions build, test, and deploy containers; automated rolling updates with health checks.
- **Maintenance Windows:** Monthly patching of underlying nodes; blue-green deployments for minimal downtime.

## 9. Conclusion and Overall Backend Summary

The vcr-online backend is a cloud-native, scalable platform built around specialized microservices. It uses proven technologies (Node.js, PostgreSQL, Redis) orchestrated by Kubernetes on AWS. Its modular API and robust database schema support emulation, streaming, device management, and archival functions. Comprehensive security, monitoring, and CI/CD processes ensure reliability and maintainability. By separating concerns—media handling, hardware control, user management—this architecture delivers both performance and flexibility, aligning directly with the project’s goal of providing an authentic, accessible online VCR experience.