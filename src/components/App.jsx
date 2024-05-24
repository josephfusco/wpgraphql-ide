import React, { useEffect, useCallback } from 'react';
import { GraphiQL } from './GraphiQL';
import { useDispatch, useSelect, dispatch } from '@wordpress/data';
import { parse, visit } from 'graphql';
import { explorerPlugin } from '@graphiql/plugin-explorer';
import { helpPanel as helpPlugin } from '../registry/activity-bar-panels/helpPanel';

import 'graphiql/graphiql.min.css';
import '../../styles/explorer.css';

const explorer = explorerPlugin();
const help = helpPlugin();

export function App() {
	const query = useSelect( ( select ) =>
		select( 'wpgraphql-ide/app' ).getQuery()
	);

	const { setQuery } = useDispatch( 'wpgraphql-ide/app' );

	const shouldRenderStandalone = useSelect( ( select ) =>
		select( 'wpgraphql-ide/app' ).shouldRenderStandalone()
	);
	const { setDrawerOpen, setSchema } = useDispatch( 'wpgraphql-ide/app' );

	const isAuthenticated = useSelect( ( select ) =>
		select( 'wpgraphql-ide/app' ).isAuthenticated()
	);

	const schema = useSelect( ( select ) =>
		select( 'wpgraphql-ide/app' ).schema()
	);

	useEffect( () => {
		// create a ref
		const ref = React.createRef();
		// find the target element in the DOM
		const element = document.querySelector(
			'[aria-label="Re-fetch GraphQL schema"]'
		);
		// if the element exists
		if ( element ) {
			// assign the ref to the element
			element.ref = ref;
			// listen to click events on the element
			element.addEventListener( 'click', () => {
				setSchema( undefined );
			} );
		}
	}, [ schema ] );

	useEffect( () => {
		localStorage.setItem(
			'graphiql:isAuthenticated',
			isAuthenticated.toString()
		);
	}, [ isAuthenticated ] );

	// const handleEditQuery = (editedQuery) => {
	// 	let update = false;

	// 	if (editedQuery === query) {
	// 	  return;
	// 	}

	// 	if (null === editedQuery || "" === editedQuery) {
	// 	  update = true;
	// 	} else {
	// 	  try {
	// 		parse(editedQuery);
	// 		update = true;
	// 	  } catch (error) {
	// 		return;
	// 	  }
	// 	}

	// 	// If the query is valid and should be updated
	// 	if (update) {
	// 	  // Update the state with the new query
	// 	  setQuery(editedQuery);
	// 	}
	// };

	const fetcher = useCallback(
		async ( graphQLParams ) => {
			let isIntrospectionQuery = false;

			try {
				// Parse the GraphQL query to AST only once and in a try-catch to handle potential syntax errors gracefully
				const queryAST = parse( graphQLParams.query );

				// Visit each node in the AST efficiently to check for introspection fields
				visit( queryAST, {
					Field( node ) {
						if (
							node.name.value === '__schema' ||
							node.name.value === '__typename'
						) {
							isIntrospectionQuery = true;
							return visit.BREAK; // Early exit if introspection query is detected
						}
					},
				} );
			} catch ( error ) {
				console.error( 'Error parsing GraphQL query:', error );
			}

			const { graphqlEndpoint } = window.WPGRAPHQL_IDE_DATA;

			const base64Credentials = btoa( `growth:growth` );

			const headers = {
				'Content-Type': 'application/json',
				Authorization: `Basic ${ base64Credentials }`,
			};

			const response = await fetch( graphqlEndpoint, {
				method: 'POST',
				headers,
				body: JSON.stringify( graphQLParams ),
				credentials: isIntrospectionQuery
					? 'include'
					: isAuthenticated
					? 'include'
					: 'omit',
			} );

			return response.json();
		},
		[ isAuthenticated ]
	);

	const activityPanels = useSelect( ( select ) => {
		const activityPanels = select( 'wpgraphql-ide/activity-bar' ).activityPanels();
		console.log( {
			activityPanels
		})
		return activityPanels;
	})


	return (
		<span id="wpgraphql-ide-app">
			<GraphiQL
				query={ query }
				fetcher={ fetcher }
				onEditQuery={ setQuery }
				schema={ schema }
				onSchemaChange={ ( newSchema ) => {
					if ( schema !== newSchema ) {
						setSchema( newSchema );
					}
				} }
				// plugins={ () => {
				// 	return [ explorer, help ];
				// 	// return useSelect( ( select ) => select( 'wpgraphql-ide/activity-bar' ).activityPanels() );
				// } }
				plugins={ activityPanels }
				// visiblePlugin={ () => {
				// 	return useSelect( ( select ) => select( 'wpgraphql-ide/activity-bar' ).visibleActivityPanel() );
				// } }
				onTogglePluginVisibility={ ( panel ) => {
					dispatch( 'wpgraphql-ide/activity-bar' ).toggleActivityPanelVisibility( panel )
				}}
			>
				<GraphiQL.Logo>
					{ ! shouldRenderStandalone && (
						<button
							className="button AppDrawerCloseButton"
							onClick={ () => setDrawerOpen( false ) }
						>
							X{ ' ' }
							<span className="screen-reader-text">
								close drawer
							</span>
						</button>
					) }
				</GraphiQL.Logo>
			</GraphiQL>
		</span>
	);
}
