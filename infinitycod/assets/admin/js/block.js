/**
 * Bloc Gutenberg « InfinityCod — Formulaire COD » (sans build).
 *
 * Rendu serveur via ServerSideRender : l'éditeur affiche le VRAI formulaire.
 * Réglages : ID du produit (vide = automatique) et titre personnalisé.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 */
(function (wp) {
	if (!wp || !wp.blocks || !wp.element || !wp.serverSideRender) { return; }

	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var ServerSideRender = wp.serverSideRender;
	var TextControl = wp.components.TextControl;
	var PanelBody = wp.components.PanelBody;
	var InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;

	registerBlockType('infinitycod/form', {
		title: 'InfinityCod — Formulaire COD',
		description: 'Formulaire de commande paiement à la livraison (rendu réel du plugin).',
		icon: 'cart',
		category: 'woocommerce',
		keywords: ['cod', 'delivery', 'infinitycod'],
		attributes: {
			id: { type: 'string', default: '' },
			title: { type: 'string', default: '' }
		},
		supports: { html: false },
		edit: function (props) {
			return [
				el(InspectorControls, { key: 'inspector' },
					el(PanelBody, { title: 'Réglages du formulaire', initialOpen: true },
						el(TextControl, {
							label: 'ID du produit',
							help: 'Laisser vide : le formulaire détecte automatiquement le produit de la page.',
							type: 'number',
							value: props.attributes.id,
							onChange: function (v) { props.setAttributes({ id: String(v || '') }); }
						}),
						el(TextControl, {
							label: 'Titre personnalisé',
							help: 'Optionnel — remplace le titre configuré dans Réglages.',
							value: props.attributes.title,
							onChange: function (v) { props.setAttributes({ title: v }); }
						})
					)
				),
				el('div', { key: 'preview', className: 'icod-block-preview' },
					el(ServerSideRender, {
						block: 'infinitycod/form',
						attributes: { id: props.attributes.id, title: props.attributes.title }
					})
				)
			];
		},
		save: function () { return null; }
	});
})(window.wp);
