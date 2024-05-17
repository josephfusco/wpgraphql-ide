import { describe, test, beforeEach } from '@playwright/test';
import { loginToWordPressAdmin, openDrawer, typeQuery } from "../utils";
import { expect } from '@playwright/test';

export const selectors = {
	graphiqlContainer: '.graphiql-container',
	graphiqlResponse: '.graphiql-response',
	editorDrawerButton: '.EditorDrawerButton',
	editorDrawerCloseButton: '.EditorDrawerCloseButton',
	executeQueryButton: '.graphiql-execute-button',
	queryInput: '[aria-label="Query Editor"] .CodeMirror',
	variablesInput: '[aria-label="Variables"] .CodeMirror',
	prettifyButton: '.graphiql-prettify-button',
	authButton: '.graphiql-toggle-auth-button',
};

// Login to WordPress before each test
beforeEach(async ({ page }) => {
	await loginToWordPressAdmin(page);
});

async function openGraphiQL(page) {
	await expect(page.locator(selectors.graphiqlContainer)).toBeHidden();
	await openDrawer(page);
}

describe('Toolbar Buttons', () => {

	beforeEach(async ({ page }) => {
		await openGraphiQL(page);
	});

	test('Clicking the Execute button executes a query', async ({ page }) => {
		await typeQuery(page, 'query { posts { nodes { title } } }');
		const response = page.locator(selectors.graphiqlResponse);
		await expect(response).not.toContainText('posts');
		await page.click(selectors.executeQueryButton);
		await expect(response).toContainText('posts');
		await expect(response).toContainText('nodes');
	});

	describe('Auth button', () => {

		beforeEach(async ({ page }) => {
			await typeQuery(page, 'query { viewer { name } }');
		});

		test('Default state is authenticated', async ({ page }) => {
			const authButton = page.locator(selectors.authButton);
			await expect(authButton).not.toHaveClass(/is-public/);
			await expect(authButton).toHaveClass(/is-authenticated/);
		});

		test('Private data is returned when authenticated', async ({ page }) => {
			const response = page.locator(selectors.graphiqlResponse);
			await expect(response).not.toContainText('viewer');
			await expect(response).not.toContainText('admin');
			await page.click(selectors.executeQueryButton);
			await expect(response).toContainText('viewer');
			await expect(response).toContainText('admin');
		});

		test('Auth button is not grayscale when authenticated', async ({ page }) => {
			const authButton = page.locator(selectors.authButton);
			const filterValue = await authButton.evaluate(node => window.getComputedStyle(node).filter);
			expect(filterValue).not.toBe('grayscale(1)');
		});

		describe('Toggling auth state to public', () => {

			beforeEach(async ({ page }) => {
				const authButton = page.locator(selectors.authButton);
				await authButton.click();
			});

			test('Auth button is in public state', async ({ page }) => {
				const authButton = page.locator(selectors.authButton);
				await expect(authButton).not.toHaveClass(/is-authenticated/);
				await expect(authButton).toHaveClass(/is-public/);
			});

			test('Private data is not returned when public', async ({ page }) => {
				const response = page.locator(selectors.graphiqlResponse);
				await page.click(selectors.executeQueryButton);
				await expect(response).toContainText('viewer');
				await expect(response).not.toContainText('admin');
			});

			test('Auth button is grayscale when public', async ({ page }) => {
				const authButton = page.locator(selectors.authButton);
				const filterValue = await authButton.evaluate(node => window.getComputedStyle(node).filter);
				expect(filterValue).toBe('grayscale(1)');
			});
		});
	});

	describe('Prettify button', () => {

		beforeEach(async ({ page }) => {
			await typeQuery(page, 'query{viewer{name}   }'); // poorly formatted query
		});

		test('Misformatted query is prettified when button is clicked', async ({ page }) => {
			const prettifyButton = page.locator(selectors.prettifyButton);
			const queryInputBeforeClick = page.locator(selectors.queryInput);

			// Ensure the query is initially poorly formatted
			await expect(queryInputBeforeClick).toContainText('query{viewer{name}   }');

			// Make sure the prettify button is visible and interactable
			await expect(prettifyButton).toBeVisible();
			await expect(prettifyButton).toBeEnabled();

			// Click the prettify button
			await prettifyButton.click();

			const queryInputAfterClick = page.locator(selectors.queryInput);

			// Verify that the query is now formatted properly
			await expect(queryInputAfterClick).toHaveText(`
{
  viewer {
    name
  }
}
			`);
		});
	});
});
