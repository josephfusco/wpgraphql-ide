import hooks from './wordpress-hooks';
import { dispatch } from '@wordpress/data';

/**
 * Public function to register a new editor toolbar button.
 *
 * @param {string} name          The name of the button to register.
 * @param {Object} config        The configuration object for the button.
 * @param {number} [priority=10] The priority for the button, lower numbers mean higher priority.
 *
 * @return {void}
 */
export function registerDocumentEditorToolbarButton(
	name,
	config,
	priority = 10
) {
	try {
		dispatch( 'wpgraphql-ide/document-editor' ).registerButton(
			name,
			config,
			priority
		);
		hooks.doAction( 'afterRegisterToolbarButton', name, config, priority );
	} catch ( error ) {
		console.error( `Failed to register button: ${ name }`, error );
		hooks.doAction(
			'registerToolbarButtonError',
			name,
			config,
			priority,
			error
		);
	}
}



