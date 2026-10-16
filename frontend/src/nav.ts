import type { PacketStatus } from './types/api';

export type View =
	| { name: 'overview' }
	| { name: 'packets' }
	| { name: 'packet'; id: number }
	| { name: 'create'; resumeId?: number }
	| { name: 'pricebooks' }
	| { name: 'pricebook'; id: number }
	| { name: 'quotes' }
	| { name: 'quote'; id: number }
	| { name: 'customers' }
	| { name: 'settings' }
	| { name: 'diagnostics' }
	| { name: 'help' };

export interface ViewProps {
	go: (view: View) => void;
}

/** Packet transitions the REST layer actually permits (PacketStateMachine). */
export const PACKET_ACTIONS: Record<string, readonly string[]> = {
	draft: ['cancel'],
	issued: ['revise', 'revoke', 'expire', 'cancel', 'archive'],
	submitted: ['revise', 'revoke', 'expire', 'cancel', 'archive'],
	in_review: ['revise', 'revoke', 'expire', 'cancel', 'archive'],
	approved: ['revise', 'revoke', 'expire', 'cancel', 'archive'],
	accepted: ['revoke', 'expire', 'cancel', 'archive'],
	ordered: ['archive'],
	expired: ['archive'],
	revoked: ['archive'],
	cancelled: ['archive'],
	archived: [],
};

export function packetAllows(status: string, action: string): boolean {
	const list = PACKET_ACTIONS[status as PacketStatus];
	return list !== undefined && list.includes(action);
}

export const PACKET_STATUSES: readonly PacketStatus[] = [
	'draft', 'issued', 'submitted', 'in_review', 'approved', 'accepted',
	'ordered', 'expired', 'revoked', 'cancelled', 'archived',
];

export const QUOTE_STATUSES: readonly string[] = [
	'requested', 'in_review', 'revised', 'approved', 'accepted',
	'ordered', 'rejected', 'superseded', 'expired', 'cancelled',
];

export const TEMPLATES: readonly { id: string; label: string; description: string }[] = [
	{
		id: 'trade_table',
		label: 'Trade table',
		description: 'Dense price-sheet layout: SKU, quantity rules, unit price and breaks in a compact table. Best for B2B reorder sheets.',
	},
	{
		id: 'visual_catalog',
		label: 'Visual catalog',
		description: 'Image-forward presentation with product photos and short descriptions. Best for curated seasonal or promotional offers.',
	},
	{
		id: 'technical_sheet',
		label: 'Technical sheet',
		description: 'Specification-first layout with product descriptions and attributes. Best for engineered or compliance-sensitive goods.',
	},
];
