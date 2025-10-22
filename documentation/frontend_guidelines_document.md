# Frontend Guideline Document for vcr-online

This document outlines the frontend architecture, design principles, and technologies used in the vcr-online project. It is written in everyday language to ensure that anyone—technical or non-technical—can understand how the frontend is set up, why certain decisions were made, and how to maintain or scale the application.

## 1. Frontend Architecture

### Overall Architecture
- **Framework:** We use React to build a fast, interactive user interface. React’s component model makes it easy to break down the UI into reusable pieces.  
- **Build Tool:** Webpack bundles our code and assets. It also handles code splitting and asset optimization.  
- **Module System:** ES Modules (import/export) keep code organized.  
- **Data Flow:** Actions flow from user events to state updates, then back to the UI. This unidirectional flow ensures predictability.

### Scalability, Maintainability, Performance
- **Scalability:** New features map naturally to new components and routes. We separate concerns (UI vs. data) so teams can work in parallel.  
- **Maintainability:**  
  - A clear folder structure (see Component Structure) makes it easy to find and update code.  
  - Strict linting and formatting rules (ESLint, Prettier) keep code consistent.  
  - Comprehensive tests (unit, integration, end-to-end) catch regressions early.  
- **Performance:**  
  - Webpack code splitting and React.lazy dynamically load only the code needed for each screen.  
  - Asset optimization (image compression, SVG icons) reduces download size.  
  - Browser caching and service workers speed up repeat visits.

## 2. Design Principles

### Key Principles
1. **Usability:** Controls (play, pause, rewind) behave as users expect. Buttons have clear labels and visual feedback.  
2. **Accessibility:** We follow WCAG guidelines. All interactive elements are keyboard-navigable and have ARIA labels. Color contrast meets recommended minimums.  
3. **Responsiveness:** The UI adapts to different screen sizes—desktop, tablet, and mobile—so users can manage their VCRs anywhere.  
4. **Consistency:** Similar actions use the same visual and interaction patterns across the app.  
5. **Familiarity:** We mimic the look and feel of a classic VCR interface, combining retro styling with modern usability.

### Applying the Principles
- **Buttons & Controls:** Large enough to tap on mobile, with hover and focus states.  
- **Forms & Inputs:** Clear labels, inline error messages, and focus outlines for keyboard users.  
- **Layouts:** Flexible grid and flexbox patterns adjust to screen width.  
- **Feedback:** Loading indicators, toaster messages, and disabled states prevent confusion.

## 3. Styling and Theming

### Styling Approach
- **Methodology:** We use BEM (Block__Element--Modifier) naming in SCSS files for clear structure.  
- **Pre-processor:** SCSS gives us variables, nesting, and mixins for reusable styles.  
- **Utility Framework:** Tailwind CSS is used sparingly for quick spacing and typography utilities.  
- **CSS Variables:** Core colors and spacing units are exposed as CSS variables, making runtime theming easy.

### Theming
- **Light & Dark Mode:** Two themes share the same variables but swap color values. A toggle in settings saves preference to local storage.  
- **Retro Overlay:** A semi-transparent glassmorphism panel mimics the VCR’s display window. This effect uses a blurred backdrop filter and a subtle noise texture.

### Visual Style
- **Overall Style:** Modern flat layout with slight glassmorphic panels for the VCR emulation display.  
- **Color Palette:**  
  - Primary (Teal): #008080  
  - Secondary (Orange): #FF8C42  
  - Accent (Crimson): #DC143C  
  - Background Light: #F4F4F4  
  - Background Dark: #1E1E1E  
  - Text Dark: #222222  
  - Text Light: #EEEEEE  
- **Fonts:**  
  - Headings: ‘Roboto Slab’, serif—gives a mechanical, retro feel.  
  - Body: ‘Roboto’, sans-serif—for readability at small sizes.  
  - Monospaced: ‘Roboto Mono’ for digital tape counters and logs.

## 4. Component Structure

### Folder Layout
```
frontend/
├── public/              # Static assets (index.html, favicon)
├── src/
│   ├── assets/          # Images, fonts, SVG icons
│   ├── components/      # Reusable React components
│   │   ├── atoms/       # Smallest UI pieces (Button, Icon)
│   │   ├── molecules/   # Combinations of atoms (ControlPanel)
│   │   └── organisms/   # Sections of a page (VcrEmulator)
│   ├── pages/           # Top-level pages (LibraryPage, EmulatorPage)
│   ├── services/        # API wrappers and utilities
│   ├── store/           # Redux store setup and slices
│   ├── styles/          # Global SCSS, variables, mixins
│   ├── utils/           # Helper functions
│   └── App.jsx          # Application root
└── package.json
```

### Reuse and Consistency
- **Component-Based Architecture:** Each UI piece lives in its own folder with its JSX, SCSS, and tests. This makes it easy to update one piece without breaking others.  
- **Storybook Integration:** Components are documented and interactively showcased in Storybook, ensuring designers and developers share a common visual language.

## 5. State Management

### Approach
- **Library:** Redux is our chosen state manager for predictable global state.  
- **Toolkit:** We use Redux Toolkit (RTK) to reduce boilerplate.  
- **Slices:** State is divided into logical domains—`userSlice`, `librarySlice`, `emulatorSlice`.  
- **Selectors & Thunks:**  
  - Selectors read computed state for performance.  
  - Thunks handle async actions (e.g., fetching video metadata or streaming tokens).

### Sharing State
- **Global State:** Stores user session, device connections, and library metadata.  
- **Local State:** Component-specific UI toggles (e.g., panel open/closed) live in React’s `useState`.  
- **Persistence:** We sync key parts of the state (theme preference, last viewed tape) to local storage.

## 6. Routing and Navigation

### Routing Library
- **React Router v6** manages routes. It supports nested routes, lazy loading, and route guards for protected pages.

### Route Structure
```jsx
<BrowserRouter>
  <Routes>
    <Route path="/login" element={<LoginPage />} />
    <Route path="/" element={<ProtectedLayout />}>  
      <Route index element={<LibraryPage />} />
      <Route path="emulator/:tapeId" element={<EmulatorPage />} />
      <Route path="devices" element={<DeviceManagementPage />} />
      <Route path="settings" element={<SettingsPage />} />
    </Route>
    <Route path="*" element={<NotFoundPage />} />
  </Routes>
</BrowserRouter>
```

### Navigation Patterns
- **Top Nav Bar:** Contains main links (Library, Devices, Settings) and user menu.  
- **Side Panel:** On larger screens, a collapsible sidebar shows recently used tapes and quick controls.  
- **Bread Crumbs:** Show the user’s location within nested pages (e.g., Home > Library > Tape).  

## 7. Performance Optimization

### Strategies
1. **Code Splitting:** Each page and some heavy components use `React.lazy` and `Suspense`.  
2. **Asset Optimization:**  
   - SVG icons in sprite sheets.  
   - Compressed images and video thumbnails.  
3. **Caching & Service Workers:**  
   - Service Worker via Workbox caches static assets and API responses for offline support.  
4. **Memoization:**  
   - `React.memo` on pure components.  
   - `useMemo` and `useCallback` for expensive calculations and callbacks.  
5. **Network:**  
   - GZIP and Brotli compression on the server.  
   - HTTP/2 to multiplex requests.

### User Experience Benefits
- Faster initial load.  
- Smooth interactions in the VCR emulator.  
- Reduced data usage on mobile connections.

## 8. Testing and Quality Assurance

### Testing Strategy
1. **Unit Tests:**  
   - Jest + React Testing Library for component logic and rendering.  
   - Aim for 80% coverage on critical slices (emulator logic, library search).  
2. **Integration Tests:**  
   - Test interactions between components (e.g., loading a tape updates the emulator display).  
3. **End-to-End (E2E) Tests:**  
   - Cypress runs through user flows: login, browse library, load tape, control emulator.  
4. **Visual Regression:**  
   - Chromatic or Percy to catch unintended CSS changes.

### Tools and Frameworks
- **ESLint & Prettier:** Enforce code style and catch common errors.  
- **Husky & lint-staged:** Run lint and tests on pre-commit.  
- **Continuous Integration:** GitHub Actions runs tests, builds previews, and reports status on pull requests.

## 9. Conclusion and Overall Frontend Summary

This document captures the frontend setup for vcr-online, from the high-level architecture to the minute styling details. We’ve chosen React for its component model, Redux for predictable state, and a modern SCSS + CSS variable theming approach to balance retro style with maintainability. Routing, performance optimizations, and comprehensive testing are baked in from day one.

Together, these guidelines ensure that vcr-online remains:
- **Maintainable:** Clear structure and conventions keep the codebase approachable.  
- **Scalable:** New features slot into existing patterns.  
- **Performant:** Lazy loading, caching, and memoization speed up the user experience.  
- **User-Friendly:** Accessibility, responsive layouts, and familiar controls put users at ease.

By following these guidelines, future developers and designers can confidently extend the vcr-online frontend without confusion, ensuring a smooth, consistent, and reliable application as it grows.