# Expose a PHP JSON API instead of server-rendering pages

The team splits by role: one engineer owns the backend, the other the frontend. Server-rendered PHP would put HTML and backend logic in the same `.php` files, so the two roles would collide on every file. Instead the backend is a set of PHP endpoints that return JSON, and the frontend is plain HTML/CSS/JS that consumes them via `fetch()`. This keeps the ownership boundary clean and is still allowed under the course constraint (native PHP + MySQL, no fullstack framework; client-side JS is fine, only Node/TypeScript are banned).

The web root is `public/`, which holds the frontend and `public/api/` (the JSON endpoints). Shared logic (`lib/`) and the database connection (`config/db.php`) live *outside* `public/` so they are never directly servable — credentials cannot leak even if PHP execution is misconfigured.

## Considered Options

Server-rendered PHP (`.html` → `.php`, loop over DB in markup) was the earlier plan and is less total code, but it tangles backend and frontend work in shared files — unworkable with separate BE/FE engineers.

## Consequences

The frontend must do its own `fetch()` and DOM rendering, which is more code than server-rendered loops. Backend slices become independently verifiable over HTTP (curl/Postman) without any frontend. ADR-0001 (availability derived from bookings) is unaffected.
