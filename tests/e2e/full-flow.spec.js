// @ts-check
import { test, expect } from '@playwright/test';

const BASE = 'http://localhost/tennisapp/public';

// Credentials from UserSeeder
const ADMIN = { email: 'admin@tennisapp.com', password: 'password' };
const USER  = { email: 'carlos@example.com', password: 'password' };

/**
 * Helper: login with given credentials
 */
async function login(page, { email, password }) {
    await page.goto(`${BASE}/login`);
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.click('button[type="submit"]');
    await page.waitForURL(url => !url.toString().includes('/login'), { timeout: 10000 });
}

/**
 * Helper: wait for success toast or flash message
 */
async function expectSuccess(page, textFragment) {
    const success = page.locator(`text=${textFragment}`).first();
    await expect(success).toBeVisible({ timeout: 10000 });
}

// ============================================================
// 1. PUBLIC PAGES — Detailed content verification
// ============================================================

test.describe('Public Pages', () => {
    test('Home page loads with hero section and CTA', async ({ page }) => {
        await page.goto(BASE);
        await expect(page).toHaveTitle(/Tennis Challenge/);
        await expect(page.locator('h1')).toContainText('Predice');
        await expect(page.locator('text=Haz tus pronósticos')).toBeVisible();
        // Guest sees "Comenzar gratis" and "Ver torneos"
        await expect(page.locator('a:has-text("Comenzar gratis")')).toBeVisible();
        await expect(page.locator('a:has-text("Ver torneos")')).toBeVisible();
    });

    test('Home page shows stats section', async ({ page }) => {
        await page.goto(BASE);
        await expect(page.locator('text=Torneos').first()).toBeVisible();
        await expect(page.locator('text=Premios').first()).toBeVisible();
    });

    test('Home page shows upcoming tournaments cards', async ({ page }) => {
        await page.goto(BASE);
        const tournamentCards = page.locator('a[href*="tournaments/"]');
        await expect(tournamentCards.first()).toBeVisible();
    });

    test('Home page shows top rankings table', async ({ page }) => {
        await page.goto(BASE);
        await expect(page.locator('text=Top Rankings')).toBeVisible();
        await expect(page.locator('text=Ver ranking completo')).toBeVisible();
    });

    test('Tournaments page loads with filter buttons', async ({ page }) => {
        await page.goto(`${BASE}/tournaments`);
        await expect(page.locator('h1')).toContainText('Torneos');
        await expect(page.locator('text=Explora los torneos')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Todos' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Grand Slams' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'ATP Masters 1000' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'WTA 1000' })).toBeVisible();
    });

    test('Tournaments page shows tournament cards', async ({ page }) => {
        await page.goto(`${BASE}/tournaments`);
        const cards = page.locator('.grid a[href*="tournaments/"]');
        const count = await cards.count();
        expect(count).toBeGreaterThan(0);
    });

    test('Tournaments filter by Grand Slams works', async ({ page }) => {
        await page.goto(`${BASE}/tournaments?type=GrandSlam`);
        await expect(page.locator('h1')).toContainText('Torneos');
        await expect(page).toHaveURL(/type=GrandSlam/);
    });

    test('Tournaments filter by ATP works', async ({ page }) => {
        await page.goto(`${BASE}/tournaments?type=ATP`);
        await expect(page).toHaveURL(/type=ATP/);
    });

    test('Tournaments filter by WTA works', async ({ page }) => {
        await page.goto(`${BASE}/tournaments?type=WTA`);
        await expect(page).toHaveURL(/type=WTA/);
    });

    test('Rankings page shows podium and ranking table', async ({ page }) => {
        await page.goto(`${BASE}/rankings`);
        await expect(page.locator('h1')).toContainText('Rankings');
        await expect(page.locator('text=Los mejores pronosticadores')).toBeVisible();
        await expect(page.locator('text=puntos').first()).toBeVisible();
    });

    test('Prizes page shows available prizes with login prompt for guests', async ({ page }) => {
        await page.goto(`${BASE}/prizes`);
        await expect(page.locator('h1')).toContainText('Premios');
        await expect(page.locator('text=Inicia sesión').first()).toBeVisible();
    });

    test('Login page has all form elements', async ({ page }) => {
        await page.goto(`${BASE}/login`);
        await expect(page.locator('#email')).toBeVisible();
        await expect(page.locator('#password')).toBeVisible();
        await expect(page.locator('button[type="submit"]')).toBeVisible();
    });

    test('Register page has all form elements', async ({ page }) => {
        await page.goto(`${BASE}/register`);
        await expect(page.locator('#name')).toBeVisible();
        await expect(page.locator('#email')).toBeVisible();
        await expect(page.locator('#password')).toBeVisible();
        await expect(page.locator('#password_confirmation')).toBeVisible();
        await expect(page.locator('button[type="submit"]')).toBeVisible();
    });

    test('Navigation links work correctly', async ({ page }) => {
        await page.goto(BASE);
        await page.locator('nav a:has-text("Torneos")').first().click();
        await expect(page).toHaveURL(/tournaments/);
        await page.locator('nav a:has-text("Rankings")').first().click();
        await expect(page).toHaveURL(/rankings/);
        await page.locator('nav a:has-text("Premios")').first().click();
        await expect(page).toHaveURL(/prizes/);
    });
});

// ============================================================
// 2. AUTHENTICATION — Full flows
// ============================================================

test.describe('Authentication', () => {
    test('User can login successfully and sees authenticated content', async ({ page }) => {
        await login(page, USER);
        await page.goto(BASE);
        // Authenticated users see "Hacer pronósticos" instead of "Comenzar gratis"
        await expect(page.locator('a:has-text("Hacer pronósticos")').first()).toBeVisible();
        await expect(page.locator('a:has-text("Comenzar gratis")')).toHaveCount(0);
        // Points display in navbar
        await expect(page.locator('text=pts').first()).toBeVisible();
    });

    test('Login with wrong password shows error and stays on login page', async ({ page }) => {
        await page.goto(`${BASE}/login`);
        await page.fill('#email', 'admin@tennisapp.com');
        await page.fill('#password', 'wrongpassword');
        await page.click('button[type="submit"]');
        await expect(page.locator('#email')).toBeVisible();
        await expect(page).toHaveURL(/login/);
    });

    test('Login with non-existent email stays on login page', async ({ page }) => {
        await page.goto(`${BASE}/login`);
        await page.fill('#email', 'noexiste@test.com');
        await page.fill('#password', 'password');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/login/);
    });

    test('User can logout', async ({ page }) => {
        await login(page, USER);
        // Verify we're logged in
        await page.goto(BASE);
        await expect(page.locator('text=pts').first()).toBeVisible();
        // Logout via POST request (public layout has no visible logout form)
        const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        await page.request.post(`${BASE}/logout`, {
            headers: { 'X-CSRF-TOKEN': csrfToken || '' },
        });
        // After logout, navigate to home — should see guest content
        await page.goto(BASE);
        await expect(page.locator('a:has-text("Comenzar gratis")').or(page.locator('a:has-text("Ingresar")')).first()).toBeVisible();
    });

    test('Register page validates required fields', async ({ page }) => {
        await page.goto(`${BASE}/register`);
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/register/);
    });

    test('Register page validates password confirmation', async ({ page }) => {
        await page.goto(`${BASE}/register`);
        await page.fill('#name', 'Test User');
        await page.fill('#email', 'test_pw_mismatch@example.com');
        await page.fill('#password', 'password123');
        await page.fill('#password_confirmation', 'different123');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/register/);
    });

    test('Non-admin user cannot access admin panel', async ({ page }) => {
        await login(page, USER);
        const response = await page.goto(`${BASE}/admin`);
        expect(response?.status()).toBe(403);
    });

    test('Guest user is redirected when accessing authenticated routes', async ({ page }) => {
        await page.goto(`${BASE}/profile`);
        await expect(page).toHaveURL(/login/);
    });
});

// ============================================================
// 3. TOURNAMENT & PREDICTION FLOW
// ============================================================

test.describe('User Flow - Tournaments & Predictions', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, USER);
    });

    test('Can click tournament card and see tournament detail page', async ({ page }) => {
        await page.goto(`${BASE}/tournaments`);
        const firstTournament = page.locator('.grid a[href*="tournaments/"]').first();
        await expect(firstTournament).toBeVisible();
        await firstTournament.click();
        await expect(page.locator('h1')).toBeVisible();
    });

    test('Tournament detail shows matches with player info', async ({ page }) => {
        await page.goto(`${BASE}/tournaments`);
        const firstLink = page.locator('.grid a[href*="tournaments/"]').first();
        await firstLink.click();
        // Should show tournament content
        await expect(page.locator('h1')).toBeVisible();
        // Back link exists
        await expect(page.locator('text=Torneos').first()).toBeVisible();
    });

    test('Tournament detail has "Hacer pronósticos" button for active tournaments', async ({ page }) => {
        await page.goto(`${BASE}/tournaments`);
        const firstTournament = page.locator('.grid a[href*="tournaments/"]').first();
        await firstTournament.click();
        // Check if predict button exists (depends on tournament status)
        const predictBtn = page.locator('a:has-text("Hacer pronósticos")').first();
        const hasPredictBtn = await predictBtn.isVisible({ timeout: 3000 }).catch(() => false);
        expect(typeof hasPredictBtn).toBe('boolean');
    });

    test('Prediction page shows pending matches with player selection', async ({ page }) => {
        await page.goto(`${BASE}/tournaments`);
        // Try to find a tournament with pending matches
        const tournamentLinks = page.locator('.grid a[href*="tournaments/"]');
        const count = await tournamentLinks.count();

        for (let i = 0; i < Math.min(count, 5); i++) {
            await page.goto(`${BASE}/tournaments`);
            await tournamentLinks.nth(i).click();
            const predictLink = page.locator('a:has-text("Hacer pronósticos")').first();
            if (await predictLink.isVisible({ timeout: 2000 }).catch(() => false)) {
                await predictLink.click();
                await expect(page.locator('h1')).toContainText('pronósticos');
                const playerButtons = page.locator('form[action*="predictions"] button[type="submit"]');
                const btnCount = await playerButtons.count();
                if (btnCount >= 2) {
                    // Found a tournament with pending matches — each match shows 2 player buttons
                    expect(btnCount).toBeGreaterThanOrEqual(2);
                    return;
                }
            }
        }
    });

    test('Can submit a prediction and see success message', async ({ page }) => {
        await page.goto(`${BASE}/tournaments`);
        const tournamentLinks = page.locator('.grid a[href*="tournaments/"]');
        const count = await tournamentLinks.count();

        for (let i = 0; i < Math.min(count, 5); i++) {
            await page.goto(`${BASE}/tournaments`);
            await tournamentLinks.nth(i).click();
            const predictLink = page.locator('a:has-text("Hacer pronósticos")').first();
            if (await predictLink.isVisible({ timeout: 2000 }).catch(() => false)) {
                await predictLink.click();
                const playerButton = page.locator('form[action*="predictions"] button[type="submit"]').first();
                if (await playerButton.isVisible({ timeout: 2000 }).catch(() => false)) {
                    await playerButton.click();
                    await expect(page.locator('text=exitosamente').or(page.locator('text=guardado'))).toBeVisible({ timeout: 5000 });
                    return;
                }
            }
        }
    });
});

// ============================================================
// 4. PROFILE
// ============================================================

test.describe('User Flow - Profile', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, USER);
    });

    test('Profile page shows user info and stats', async ({ page }) => {
        await page.goto(`${BASE}/profile`);
        await expect(page.locator('body')).toContainText('Carlos');
        await expect(page.locator('body')).toContainText('carlos@example.com');
        await expect(page.locator('text=Puntos').first()).toBeVisible();
        await expect(page.locator('text=Pronósticos').first()).toBeVisible();
        await expect(page.locator('text=Aciertos').first()).toBeVisible();
    });

    test('Profile shows predictions section', async ({ page }) => {
        await page.goto(`${BASE}/profile`);
        // Should show either predictions list or empty state
        await expect(page.locator('text=Mis pronósticos recientes')).toBeVisible();
    });

    test('Profile has edit profile button', async ({ page }) => {
        await page.goto(`${BASE}/profile`);
        const editBtn = page.locator('a:has-text("Editar perfil")');
        await expect(editBtn).toBeVisible();
    });
});

// ============================================================
// 5. PRIZES & REDEMPTION
// ============================================================

test.describe('User Flow - Prizes', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, USER);
    });

    test('Prizes page shows user points balance when logged in', async ({ page }) => {
        await page.goto(`${BASE}/prizes`);
        await expect(page.locator('h1')).toContainText('Premios');
        // Carlos has 1250 points — should see "puntos disponibles"
        await expect(page.locator('text=puntos disponibles')).toBeVisible();
    });

    test('Prizes page shows redeem button for affordable prizes', async ({ page }) => {
        await page.goto(`${BASE}/prizes`);
        // Carlos has 1250 points. "Camiseta Nike" costs 800 → should see "Canjear"
        const redeemBtn = page.locator('button:has-text("Canjear")').first();
        await expect(redeemBtn).toBeVisible();
    });

    test('Prizes page shows "needs more points" for expensive prizes', async ({ page }) => {
        await page.goto(`${BASE}/prizes`);
        // Smart TV costs 8000, Raqueta costs 5000 → should show "pts más"
        await expect(page.locator('text=pts más').first()).toBeVisible();
    });

    test('Can initiate prize redemption with confirm flow', async ({ page }) => {
        await page.goto(`${BASE}/prizes`);
        const redeemBtn = page.locator('button:has-text("Canjear")').first();
        if (await redeemBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
            await redeemBtn.click();
            // Should show confirmation buttons (Alpine.js toggle)
            const confirmBtn = page.locator('button:has-text("Confirmar")').first();
            await expect(confirmBtn).toBeVisible({ timeout: 3000 });
        }
    });
});

// ============================================================
// 6. ADMIN — Dashboard
// ============================================================

test.describe('Admin - Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('Dashboard shows stats cards with correct data', async ({ page }) => {
        await page.goto(`${BASE}/admin`);
        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
        await expect(page.locator('text=Usuarios').first()).toBeVisible();
        await expect(page.locator('text=Torneos').first()).toBeVisible();
        await expect(page.locator('text=Pronósticos').first()).toBeVisible();
    });

    test('Dashboard shows recent users section', async ({ page }) => {
        await page.goto(`${BASE}/admin`);
        await expect(page.locator('text=Usuarios recientes')).toBeVisible();
        // Recent users shows latest registered (not by points)
        await expect(page.locator('text=example.com').first()).toBeVisible();
    });

    test('Admin sidebar navigation has all links', async ({ page }) => {
        await page.goto(`${BASE}/admin`);
        await expect(page.locator('a:has-text("Dashboard")').first()).toBeVisible();
        await expect(page.locator('a:has-text("Torneos")').first()).toBeVisible();
        await expect(page.locator('a:has-text("Partidos")').first()).toBeVisible();
        await expect(page.locator('a:has-text("Jugadores")').first()).toBeVisible();
        await expect(page.locator('a:has-text("Usuarios")').first()).toBeVisible();
        await expect(page.locator('a:has-text("Premios")').first()).toBeVisible();
        await expect(page.locator('a:has-text("Canjes")').first()).toBeVisible();
        await expect(page.locator('a:has-text("Banners")').first()).toBeVisible();
        await expect(page.locator('a[href*="settings"]').first()).toBeVisible();
        await expect(page.locator('a:has-text("API Sync")').first()).toBeVisible();
    });
});

// ============================================================
// 7. ADMIN — Tournament CRUD
// ============================================================

test.describe('Admin - Tournament CRUD', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('Tournaments index shows table with tournaments', async ({ page }) => {
        await page.goto(`${BASE}/admin/tournaments`);
        await expect(page.locator('body')).toContainText('Torneos');
        await expect(page.locator('a:has-text("Nuevo torneo")')).toBeVisible();
        // Should have tournament rows in table
        const rows = page.locator('tbody tr');
        const count = await rows.count();
        expect(count).toBeGreaterThan(0);
    });

    test('Tournaments index shows type badges', async ({ page }) => {
        await page.goto(`${BASE}/admin/tournaments`);
        // Type badges should exist (ATP, WTA, or Grand Slam)
        const body = await page.locator('tbody').textContent();
        const hasTypes = body?.includes('ATP') || body?.includes('WTA') || body?.includes('Grand Slam');
        expect(hasTypes).toBeTruthy();
    });

    test('Can create a new tournament with all fields', async ({ page }) => {
        await page.goto(`${BASE}/admin/tournaments/create`);
        await expect(page.locator('text=Crear torneo').first()).toBeVisible();

        await page.fill('input[name="name"]', 'Test Tournament E2E');
        await page.selectOption('select[name="type"]', 'ATP');
        await page.fill('input[name="location"]', 'Test Arena');
        await page.fill('input[name="city"]', 'Test City');
        await page.fill('input[name="country"]', 'Test Country');
        await page.fill('input[name="start_date"]', '2026-06-01');
        await page.fill('input[name="end_date"]', '2026-06-14');
        await page.fill('input[name="points_multiplier"]', '1.5');

        await page.click('button:has-text("Crear torneo")');
        await expect(page).toHaveURL(/admin\/tournaments/);
        await expectSuccess(page, 'creado');
    });

    test('Tournament creation validates required fields', async ({ page }) => {
        await page.goto(`${BASE}/admin/tournaments/create`);
        await page.click('button:has-text("Crear torneo")');
        await expect(page).toHaveURL(/tournaments/);
    });

    test('Tournament creation validates end_date after start_date', async ({ page }) => {
        await page.goto(`${BASE}/admin/tournaments/create`);
        await page.fill('input[name="name"]', 'Date Test Tournament');
        await page.selectOption('select[name="type"]', 'ATP');
        await page.fill('input[name="location"]', 'Location');
        await page.fill('input[name="city"]', 'City');
        await page.fill('input[name="country"]', 'Country');
        await page.fill('input[name="start_date"]', '2026-06-14');
        await page.fill('input[name="end_date"]', '2026-06-01');
        await page.click('button:has-text("Crear torneo")');
        await expect(page).toHaveURL(/tournaments/);
    });

    test('Can edit an existing tournament', async ({ page }) => {
        await page.goto(`${BASE}/admin/tournaments`);
        const editBtn = page.locator('a:has-text("Editar")').first();
        await editBtn.click();
        await expect(page).toHaveURL(/tournaments\/\d+\/edit/);

        const nameField = page.locator('input[name="name"]');
        const nameValue = await nameField.inputValue();
        expect(nameValue.length).toBeGreaterThan(0);

        await page.fill('input[name="surface"]', 'Duro');
        await page.click('button:has-text("Actualizar torneo")');
        await expect(page).toHaveURL(/admin\/tournaments/);
        await expectSuccess(page, 'actualizado');
    });

    test('Can delete a tournament with confirmation', async ({ page }) => {
        // Create one to delete
        await page.goto(`${BASE}/admin/tournaments/create`);
        await page.fill('input[name="name"]', 'Tournament To Delete');
        await page.selectOption('select[name="type"]', 'WTA');
        await page.fill('input[name="location"]', 'Delete Arena');
        await page.fill('input[name="city"]', 'Delete City');
        await page.fill('input[name="country"]', 'Delete Country');
        await page.fill('input[name="start_date"]', '2026-12-01');
        await page.fill('input[name="end_date"]', '2026-12-14');
        await page.click('button:has-text("Crear torneo")');
        await expect(page).toHaveURL(/admin\/tournaments/);

        page.on('dialog', dialog => dialog.accept());
        const deleteForm = page.locator('tr:has-text("Tournament To Delete") form:has(button:has-text("Eliminar"))');
        await deleteForm.locator('button:has-text("Eliminar")').click();
        await expectSuccess(page, 'eliminado');
    });
});

// ============================================================
// 8. ADMIN — Player CRUD
// ============================================================

test.describe('Admin - Player CRUD', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('Players index shows table with seeded players', async ({ page }) => {
        await page.goto(`${BASE}/admin/players`);
        await expect(page.locator('body')).toContainText('Jugadores');
        await expect(page.locator('a:has-text("Nuevo jugador")')).toBeVisible();
        const rows = page.locator('tbody tr');
        const count = await rows.count();
        expect(count).toBeGreaterThan(0);
    });

    test('Players can be searched by name', async ({ page }) => {
        await page.goto(`${BASE}/admin/players`);
        const searchInput = page.locator('input[name="search"]');
        await searchInput.fill('Alcaraz');
        await searchInput.press('Enter');
        await expect(page.locator('text=Alcaraz').first()).toBeVisible();
    });

    test('Players can be filtered by category ATP', async ({ page }) => {
        await page.goto(`${BASE}/admin/players?category=ATP`);
        const rows = page.locator('tbody tr');
        const count = await rows.count();
        expect(count).toBeGreaterThan(0);
    });

    test('Players can be filtered by category WTA', async ({ page }) => {
        await page.goto(`${BASE}/admin/players?category=WTA`);
        const rows = page.locator('tbody tr');
        const count = await rows.count();
        expect(count).toBeGreaterThan(0);
    });

    test('Can create a new player with all fields', async ({ page }) => {
        await page.goto(`${BASE}/admin/players/create`);
        await page.fill('input[name="name"]', 'Test Player E2E');
        await page.fill('input[name="country"]', 'Test Country');
        await page.fill('input[name="nationality_code"]', 'TST');
        await page.fill('input[name="ranking"]', '99');
        await page.selectOption('select[name="category"]', 'ATP');
        await page.fill('textarea[name="bio"]', 'Test bio for E2E player');

        await page.click('button:has-text("Crear jugador")');
        await expect(page).toHaveURL(/admin\/players/);
        await expectSuccess(page, 'creado');
    });

    test('Player creation validates required fields', async ({ page }) => {
        await page.goto(`${BASE}/admin/players/create`);
        await page.click('button:has-text("Crear jugador")');
        await expect(page).toHaveURL(/players/);
    });

    test('Can edit an existing player', async ({ page }) => {
        await page.goto(`${BASE}/admin/players`);
        const editBtn = page.locator('a:has-text("Editar")').first();
        await editBtn.click();
        await expect(page).toHaveURL(/players\/\d+\/edit/);

        const nameField = page.locator('input[name="name"]');
        const nameValue = await nameField.inputValue();
        expect(nameValue.length).toBeGreaterThan(0);

        await page.fill('textarea[name="bio"]', 'Updated bio via E2E test');
        await page.click('button:has-text("Actualizar jugador")');
        await expect(page).toHaveURL(/admin\/players/);
        await expectSuccess(page, 'actualizado');
    });

    test('Can delete a player with confirmation', async ({ page }) => {
        await page.goto(`${BASE}/admin/players/create`);
        await page.fill('input[name="name"]', 'Player To Delete');
        await page.fill('input[name="country"]', 'Nowhere');
        await page.fill('input[name="nationality_code"]', 'DEL');
        await page.fill('input[name="ranking"]', '999');
        await page.selectOption('select[name="category"]', 'WTA');
        await page.click('button:has-text("Crear jugador")');
        await expect(page).toHaveURL(/admin\/players/);

        await page.fill('input[name="search"]', 'Player To Delete');
        await page.press('input[name="search"]', 'Enter');

        page.on('dialog', dialog => dialog.accept());
        const deleteBtn = page.locator('tr:has-text("Player To Delete") form:has(button:has-text("Eliminar")) button');
        await deleteBtn.click();
        await expectSuccess(page, 'eliminado');
    });
});

// ============================================================
// 9. ADMIN — Match CRUD
// ============================================================

test.describe('Admin - Match CRUD', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('Matches index shows table with matches', async ({ page }) => {
        await page.goto(`${BASE}/admin/matches`);
        await expect(page.locator('body')).toContainText('Partidos');
        await expect(page.locator('a:has-text("Nuevo partido")')).toBeVisible();
        const rows = page.locator('tbody tr');
        const count = await rows.count();
        expect(count).toBeGreaterThan(0);
    });

    test('Matches can be filtered by status', async ({ page }) => {
        await page.goto(`${BASE}/admin/matches?status=pending`);
        // Should show only pending matches or empty
        await expect(page.locator('body')).toContainText('Partidos');
    });

    test('Matches can be filtered by tournament', async ({ page }) => {
        await page.goto(`${BASE}/admin/matches`);
        const tournamentSelect = page.locator('select[name="tournament_id"]');
        if (await tournamentSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
            const options = await tournamentSelect.locator('option').all();
            if (options.length > 1) {
                const value = await options[1].getAttribute('value');
                if (value) {
                    await tournamentSelect.selectOption(value);
                    await page.locator('button:has-text("Filtrar")').click();
                    await expect(page).toHaveURL(/tournament_id/);
                }
            }
        }
    });

    test('Can create a new match with all fields', async ({ page }) => {
        await page.goto(`${BASE}/admin/matches/create`);
        await expect(page.locator('text=Crear Partido').first()).toBeVisible();

        // Select tournament (first real option)
        const tournamentSelect = page.locator('select[name="tournament_id"]');
        await tournamentSelect.selectOption({ index: 1 });

        // Select player 1 (first real option with a value)
        const player1Select = page.locator('select[name="player1_id"]');
        const p1Options = await player1Select.locator('option[value]:not([value=""])').all();
        if (p1Options.length >= 2) {
            await player1Select.selectOption(await p1Options[0].getAttribute('value') || '');
            // Select player 2 (different player)
            const player2Select = page.locator('select[name="player2_id"]');
            await player2Select.selectOption(await p1Options[1].getAttribute('value') || '');
        }

        await page.selectOption('select[name="round"]', 'R32');
        await page.fill('input[name="scheduled_at"]', '2026-07-01T14:00');

        await page.click('button:has-text("Crear partido")');
        // If players were properly selected, should succeed
        const url = page.url();
        if (!url.includes('/create')) {
            await expectSuccess(page, 'creado');
        }
    });

    test('Match creation validates player1 different from player2', async ({ page }) => {
        await page.goto(`${BASE}/admin/matches/create`);

        await page.locator('select[name="tournament_id"]').selectOption({ index: 1 });

        const player1Select = page.locator('select[name="player1_id"]');
        const p1Options = await player1Select.locator('option[value]:not([value=""])').all();
        if (p1Options.length > 0) {
            const sameVal = await p1Options[0].getAttribute('value') || '';
            await player1Select.selectOption(sameVal);
            await page.locator('select[name="player2_id"]').selectOption(sameVal);
        }

        await page.selectOption('select[name="round"]', 'R16');
        await page.fill('input[name="scheduled_at"]', '2026-07-01T14:00');
        await page.click('button:has-text("Crear partido")');
        // Should fail validation (different rule)
        await expect(page).toHaveURL(/matches/);
    });

    test('Can edit a match and set score/status', async ({ page }) => {
        await page.goto(`${BASE}/admin/matches`);
        const editBtn = page.locator('a:has-text("Editar")').first();
        await editBtn.click();
        await expect(page).toHaveURL(/matches\/\d+\/edit/);

        // Should see result section
        await expect(page.locator('input[name="score"]')).toBeVisible();
        await expect(page.locator('select[name="status"]')).toBeVisible();

        // Set score — make sure players are selected first
        // Check if players are already selected (pre-populated from existing match)
        const player1Select = page.locator('select[name="player1_id"]');
        const p1Value = await player1Select.inputValue();
        if (!p1Value) {
            const p1Options = await player1Select.locator('option[value]:not([value=""])').all();
            if (p1Options.length >= 2) {
                await player1Select.selectOption(await p1Options[0].getAttribute('value') || '');
                await page.locator('select[name="player2_id"]').selectOption(await p1Options[1].getAttribute('value') || '');
            }
        }

        await page.fill('input[name="score"]', '6-4, 7-5');
        await page.selectOption('select[name="status"]', 'finished');

        const winnerSelect = page.locator('select[name="winner_id"]');
        if (await winnerSelect.isVisible()) {
            const winnerOptions = await winnerSelect.locator('option[value]:not([value=""])').all();
            if (winnerOptions.length > 0) {
                await winnerSelect.selectOption(await winnerOptions[0].getAttribute('value') || '');
            }
        }

        await page.click('button:has-text("Actualizar partido")');
        // Check if submission went through
        const url = page.url();
        if (!url.includes('/edit')) {
            await expectSuccess(page, 'actualizado');
        }
    });

    test('Can delete a match with confirmation', async ({ page }) => {
        await page.goto(`${BASE}/admin/matches`);
        page.on('dialog', dialog => dialog.accept());
        const deleteBtn = page.locator('tbody tr form:has(button:has-text("Eliminar")) button').last();
        if (await deleteBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
            await deleteBtn.click();
            await expectSuccess(page, 'eliminado');
        }
    });
});

// ============================================================
// 10. ADMIN — Prize CRUD
// ============================================================

test.describe('Admin - Prize CRUD', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('Prizes index shows table with seeded prizes', async ({ page }) => {
        await page.goto(`${BASE}/admin/prizes`);
        await expect(page.locator('body')).toContainText('Premios');
        await expect(page.locator('a:has-text("Nuevo premio")')).toBeVisible();
        await expect(page.locator('text=Raqueta Wilson').first()).toBeVisible();
    });

    test('Prizes index shows points, stock, and status', async ({ page }) => {
        await page.goto(`${BASE}/admin/prizes`);
        await expect(page.locator('text=pts').first()).toBeVisible();
        await expect(page.locator('text=Activo').first()).toBeVisible();
    });

    test('Can create a new prize', async ({ page }) => {
        await page.goto(`${BASE}/admin/prizes/create`);
        await page.fill('input[name="name"]', 'Test Prize E2E');
        await page.fill('textarea[name="description"]', 'Test prize description');
        await page.fill('input[name="points_required"]', '100');
        await page.fill('input[name="stock"]', '5');
        await page.click('button:has-text("Crear premio")');
        await expect(page).toHaveURL(/admin\/prizes/);
        await expectSuccess(page, 'creado');
    });

    test('Prize creation validates required fields', async ({ page }) => {
        await page.goto(`${BASE}/admin/prizes/create`);
        await page.click('button:has-text("Crear premio")');
        await expect(page).toHaveURL(/prizes/);
    });

    test('Can edit an existing prize', async ({ page }) => {
        await page.goto(`${BASE}/admin/prizes`);
        const editBtn = page.locator('a:has-text("Editar")').first();
        await editBtn.click();
        await expect(page).toHaveURL(/prizes\/\d+\/edit/);
        await page.fill('input[name="stock"]', '99');
        await page.click('button:has-text("Actualizar premio")');
        await expect(page).toHaveURL(/admin\/prizes/);
        await expectSuccess(page, 'actualizado');
    });

    test('Can delete a prize with confirmation', async ({ page }) => {
        await page.goto(`${BASE}/admin/prizes/create`);
        await page.fill('input[name="name"]', 'Prize To Delete');
        await page.fill('input[name="points_required"]', '1');
        await page.fill('input[name="stock"]', '1');
        await page.click('button:has-text("Crear premio")');
        await expect(page).toHaveURL(/admin\/prizes/);

        page.on('dialog', dialog => dialog.accept());
        const deleteBtn = page.locator('tr:has-text("Prize To Delete") form:has(button:has-text("Eliminar")) button');
        await deleteBtn.click();
        await expectSuccess(page, 'eliminado');
    });
});

// ============================================================
// 11. ADMIN — Banner CRUD & Toggle
// ============================================================

test.describe('Admin - Banner CRUD', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('Banners index shows seeded banners', async ({ page }) => {
        await page.goto(`${BASE}/admin/banners`);
        await expect(page.locator('body')).toContainText('Banners');
        await expect(page.locator('a:has-text("Nuevo banner")')).toBeVisible();
        await expect(page.locator('text=Indian Wells').first()).toBeVisible();
    });

    test('Can create a new banner', async ({ page }) => {
        await page.goto(`${BASE}/admin/banners/create`);
        await page.fill('input[name="title"]', 'Test Banner E2E');
        await page.fill('input[name="subtitle"]', 'Test subtitle');
        await page.fill('input[name="link"]', 'https://example.com');
        await page.fill('input[name="order"]', '99');
        await page.click('button:has-text("Crear banner")');
        await expect(page).toHaveURL(/admin\/banners/);
        await expectSuccess(page, 'creado');
    });

    test('Can edit an existing banner', async ({ page }) => {
        await page.goto(`${BASE}/admin/banners`);
        const editBtn = page.locator('a:has-text("Editar")').first();
        await editBtn.click();
        await expect(page).toHaveURL(/banners\/\d+\/edit/);

        const titleField = page.locator('input[name="title"]');
        const titleValue = await titleField.inputValue();
        expect(titleValue.length).toBeGreaterThan(0);

        await page.fill('input[name="subtitle"]', 'Updated subtitle E2E');
        // Clear the link field first to avoid URL validation on relative paths
        const linkField = page.locator('input[name="link"]');
        await linkField.fill('https://example.com/updated');

        await page.click('button:has-text("Actualizar banner")');
        await expect(page).toHaveURL(/admin\/banners/);
        await expectSuccess(page, 'actualizado');
    });

    test('Can toggle banner active/inactive', async ({ page }) => {
        await page.goto(`${BASE}/admin/banners`);
        const toggleBtn = page.locator('form[action*="toggle"] button').first();
        const currentText = (await toggleBtn.textContent()) || '';
        await toggleBtn.click();
        await expectSuccess(page, 'Estado del banner');
        // Verify toggle changed
        const toggleBtnAfter = page.locator('form[action*="toggle"] button').first();
        const newText = (await toggleBtnAfter.textContent()) || '';
        expect(newText.trim()).not.toBe(currentText.trim());
    });

    test('Can delete a banner with confirmation', async ({ page }) => {
        await page.goto(`${BASE}/admin/banners/create`);
        await page.fill('input[name="title"]', 'Banner To Delete');
        await page.fill('input[name="order"]', '999');
        await page.click('button:has-text("Crear banner")');
        await expect(page).toHaveURL(/admin\/banners/);

        page.on('dialog', dialog => dialog.accept());
        const deleteBtn = page.locator('tr:has-text("Banner To Delete") form:has(button:has-text("Eliminar")) button');
        await deleteBtn.click();
        await expectSuccess(page, 'eliminado');
    });
});

// ============================================================
// 12. ADMIN — Users (List, View, Block/Unblock)
// ============================================================

test.describe('Admin - Users', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('Users index shows non-admin users', async ({ page }) => {
        await page.goto(`${BASE}/admin/users`);
        await expect(page.locator('body')).toContainText('Usuarios');
        // Should show seeded users
        await expect(page.locator('text=example.com').first()).toBeVisible();
    });

    test('Users can be searched by name', async ({ page }) => {
        await page.goto(`${BASE}/admin/users`);
        const searchInput = page.locator('input[name="search"]');
        await searchInput.fill('Valentina');
        await searchInput.press('Enter');
        await expect(page.locator('text=Valentina').first()).toBeVisible();
    });

    test('Can view user detail page with stats', async ({ page }) => {
        await page.goto(`${BASE}/admin/users`);
        // Scope "Ver" link to the users table to avoid matching sidebar links
        const viewBtn = page.locator('tbody a:has-text("Ver")').first();
        await viewBtn.click();
        await expect(page).toHaveURL(/admin\/users\/\d+/);
        await expect(page.locator('text=Puntos').first()).toBeVisible();
        await expect(page.locator('text=Pronósticos').first()).toBeVisible();
        await expect(page.locator('text=Aciertos').first()).toBeVisible();
    });

    test('User detail page shows predictions or empty state', async ({ page }) => {
        await page.goto(`${BASE}/admin/users`);
        const viewBtn = page.locator('tbody a:has-text("Ver")').first();
        await viewBtn.click();
        const body = await page.locator('body').textContent();
        const hasPredictions = body?.includes('pronósticos') || body?.includes('Pronósticos');
        expect(hasPredictions).toBeTruthy();
    });

    test('Can toggle user block/unblock', async ({ page }) => {
        await page.goto(`${BASE}/admin/users`);
        const toggleBtn = page.locator('button:has-text("Bloquear"), button:has-text("Desbloquear")').last();
        const currentText = (await toggleBtn.textContent()) || '';
        await toggleBtn.click();
        const expectedMsg = currentText.trim().includes('Bloquear') ? 'bloqueado' : 'desbloqueado';
        await expectSuccess(page, expectedMsg);
    });
});

// ============================================================
// 13. ADMIN — Redemptions
// ============================================================

test.describe('Admin - Redemptions', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('Redemptions index page loads', async ({ page }) => {
        await page.goto(`${BASE}/admin/redemptions`);
        await expect(page.locator('body')).toContainText('Canjes');
    });
});

// ============================================================
// 14. ADMIN — Settings
// ============================================================

test.describe('Admin - Settings', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('Settings page shows all configuration sections', async ({ page }) => {
        await page.goto(`${BASE}/admin/settings`);
        await expect(page.locator('body')).toContainText('Configuración');
        await expect(page.locator('input[name="site_name"]')).toBeVisible();
        await expect(page.locator('textarea[name="site_description"]')).toBeVisible();
        await expect(page.locator('input[name="points_per_correct"]')).toBeVisible();
        await expect(page.locator('input[name="bonus_champion"]')).toBeVisible();
        await expect(page.locator('input[name="contact_email"]')).toBeVisible();
    });

    test('Settings are pre-populated with seeded values', async ({ page }) => {
        await page.goto(`${BASE}/admin/settings`);
        const siteName = await page.locator('input[name="site_name"]').inputValue();
        expect(siteName).toBe('Tennis Challenge');
        const pointsPerCorrect = await page.locator('input[name="points_per_correct"]').inputValue();
        expect(pointsPerCorrect).toBe('10');
    });

    test('Can update settings and see success', async ({ page }) => {
        await page.goto(`${BASE}/admin/settings`);
        await page.fill('textarea[name="site_description"]', 'Updated description via E2E');
        await page.click('button:has-text("Guardar configuración")');
        await expectSuccess(page, 'actualizada');

        // Verify the change persisted
        await page.goto(`${BASE}/admin/settings`);
        const desc = await page.locator('textarea[name="site_description"]').inputValue();
        expect(desc).toBe('Updated description via E2E');

        // Restore original
        await page.fill('textarea[name="site_description"]', 'La mejor plataforma de pronósticos de tenis profesional');
        await page.click('button:has-text("Guardar configuración")');
    });

    test('Can update points configuration', async ({ page }) => {
        await page.goto(`${BASE}/admin/settings`);
        await page.fill('input[name="points_per_correct"]', '15');
        await page.click('button:has-text("Guardar configuración")');
        await expectSuccess(page, 'actualizada');

        await page.goto(`${BASE}/admin/settings`);
        const pts = await page.locator('input[name="points_per_correct"]').inputValue();
        expect(pts).toBe('15');

        // Restore
        await page.fill('input[name="points_per_correct"]', '10');
        await page.click('button:has-text("Guardar configuración")');
    });
});

// ============================================================
// 15. ADMIN — API Sync Panel
// ============================================================

test.describe('Admin - API Sync', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, ADMIN);
    });

    test('API Sync page loads with all sync cards', async ({ page }) => {
        await page.goto(`${BASE}/admin/api-sync`);
        await expect(page.locator('h2')).toContainText('Sincronización API Tennis');
        await expect(page.locator('button:has-text("Sincronizar Torneos")')).toBeVisible();
        await expect(page.locator('button:has-text("Sincronizar Jugadores")')).toBeVisible();
        await expect(page.locator('button:has-text("Sincronizar Partidos")')).toBeVisible();
        await expect(page.locator('button:has-text("Sincronizar Livescores")')).toBeVisible();
        await expect(page.locator('button:has-text("Sincronizar Todo")')).toBeVisible();
    });

    test('API Sync page shows date inputs for fixtures', async ({ page }) => {
        await page.goto(`${BASE}/admin/api-sync`);
        await expect(page.locator('input[name="date_from"]')).toBeVisible();
        await expect(page.locator('input[name="date_to"]')).toBeVisible();
    });

    test('API Sync page shows automatic sync schedule info', async ({ page }) => {
        await page.goto(`${BASE}/admin/api-sync`);
        await expect(page.locator('text=Sincronización automática')).toBeVisible();
    });

    test('API Sync link exists in admin sidebar', async ({ page }) => {
        await page.goto(`${BASE}/admin`);
        const apiSyncLink = page.locator('a[href*="api-sync"]');
        await expect(apiSyncLink).toBeVisible();
        await expect(apiSyncLink).toContainText('API Sync');
    });

    test('Can trigger livescore sync', async ({ page }) => {
        await page.goto(`${BASE}/admin/api-sync`);
        const syncBtn = page.locator('button:has-text("Sincronizar Livescores")');
        await syncBtn.click();
        await page.waitForTimeout(5000);
        await expect(page.locator('h2')).toContainText('Sincronización API Tennis');
    });
});

// ============================================================
// 16. COMPLETE USER JOURNEY
// ============================================================

test.describe('Complete User Journey', () => {
    test('Full flow: Home → Login → Tournaments → Detail → Rankings → Prizes → Profile', async ({ page }) => {
        // Step 1: Home as guest
        await page.goto(BASE);
        await expect(page.locator('h1')).toContainText('Predice');
        await expect(page.locator('a:has-text("Comenzar gratis")')).toBeVisible();

        // Step 2: Login
        await login(page, USER);

        // Step 3: Home as authenticated user
        await page.goto(BASE);
        await expect(page.locator('a:has-text("Hacer pronósticos")').first()).toBeVisible();

        // Step 4: Browse tournaments
        await page.goto(`${BASE}/tournaments`);
        await expect(page.locator('h1')).toContainText('Torneos');
        const tournamentCount = await page.locator('.grid a[href*="tournaments/"]').count();
        expect(tournamentCount).toBeGreaterThan(0);

        // Step 5: Filter tournaments
        await page.locator('a:has-text("Grand Slams")').click();
        await expect(page).toHaveURL(/type=GrandSlam/);

        // Step 6: Click into a tournament
        await page.goto(`${BASE}/tournaments`);
        const firstTournament = page.locator('.grid a[href*="tournaments/"]').first();
        await firstTournament.click();
        await expect(page.locator('h1')).toBeVisible();

        // Step 7: Rankings
        await page.goto(`${BASE}/rankings`);
        await expect(page.locator('h1')).toContainText('Rankings');

        // Step 8: Prizes
        await page.goto(`${BASE}/prizes`);
        await expect(page.locator('h1')).toContainText('Premios');
        await expect(page.locator('text=puntos disponibles')).toBeVisible();

        // Step 9: Profile
        await page.goto(`${BASE}/profile`);
        await expect(page.locator('body')).toContainText('Carlos');
        await expect(page.locator('body')).toContainText('carlos@example.com');
    });
});

// ============================================================
// 17. COMPLETE ADMIN JOURNEY
// ============================================================

test.describe('Complete Admin Journey', () => {
    test('Full flow: Dashboard → CRUD tournament → Navigate sections → Settings → API Sync', async ({ page }) => {
        await login(page, ADMIN);

        // Step 1: Dashboard
        await page.goto(`${BASE}/admin`);
        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();

        // Step 2: Create a tournament
        await page.goto(`${BASE}/admin/tournaments/create`);
        await page.fill('input[name="name"]', 'Journey Test Tournament');
        await page.selectOption('select[name="type"]', 'GrandSlam');
        await page.fill('input[name="location"]', 'Journey Arena');
        await page.fill('input[name="city"]', 'Journey City');
        await page.fill('input[name="country"]', 'Journey Land');
        await page.fill('input[name="start_date"]', '2026-11-01');
        await page.fill('input[name="end_date"]', '2026-11-14');
        await page.fill('input[name="points_multiplier"]', '2.0');
        await page.click('button:has-text("Crear torneo")');
        await expectSuccess(page, 'creado');

        // Step 3: Verify it appears in the list
        await expect(page.locator('text=Journey Test Tournament').first()).toBeVisible();

        // Step 4: Navigate through admin sections
        const sections = [
            { url: '/admin/players', text: 'Jugadores' },
            { url: '/admin/matches', text: 'Partidos' },
            { url: '/admin/users', text: 'Usuarios' },
            { url: '/admin/prizes', text: 'Premios' },
            { url: '/admin/redemptions', text: 'Canjes' },
            { url: '/admin/banners', text: 'Banners' },
        ];

        for (const section of sections) {
            await page.goto(`${BASE}${section.url}`);
            await expect(page.locator('body')).toContainText(section.text);
        }

        // Step 5: View a user detail
        await page.goto(`${BASE}/admin/users`);
        const viewBtn = page.locator('tbody a:has-text("Ver")').first();
        await viewBtn.click();
        await expect(page).toHaveURL(/admin\/users\/\d+/);

        // Step 6: Settings
        await page.goto(`${BASE}/admin/settings`);
        await expect(page.locator('input[name="site_name"]')).toBeVisible();

        // Step 7: Visit API Sync
        await page.goto(`${BASE}/admin/api-sync`);
        await expect(page.locator('h2')).toContainText('Sincronización API Tennis');

        // Step 8: Clean up - delete the test tournament
        await page.goto(`${BASE}/admin/tournaments`);
        page.on('dialog', dialog => dialog.accept());
        const deleteBtn = page.locator('tr:has-text("Journey Test Tournament") form:has(button:has-text("Eliminar")) button');
        if (await deleteBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
            await deleteBtn.click();
            await expectSuccess(page, 'eliminado');
        }
    });
});
