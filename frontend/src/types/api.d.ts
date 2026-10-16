/**
 * Domain types mirroring the plugin's REST API responses exactly.
 * Source of truth: plugin/tradesheet-studio/src/Api/*.php
 */

export interface Customer {
	id: number;
	display_name: string;
	email: string;
	billing_company: string;
	pricebook_id: number | null;
}

export interface CustomersResponse {
	items: Customer[];
	total: number;
}

export interface Product {
	id: number;
	name: string;
	sku: string;
	type: string;
	price: string;
	image_id: number;
	stock_status: string;
	categories: number[];
}

export interface ProductsResponse {
	items: Product[];
	total: number;
	currency_symbol: string;
}

export interface Variation {
	variation_id: number;
	product_id: number;
	sku: string;
	summary: string;
	price: string;
	stock_status: string;
	image_id: number;
}

export interface VariationsResponse {
	items: Variation[];
}

export interface QtyBreak {
	qty: number;
	price: string;
}

export type PriceBookStatus = 'active' | 'inactive';

export interface PriceBook {
	id: number;
	name: string;
	description: string;
	status: PriceBookStatus | string;
	currency: string;
	priority: number;
	valid_from: string | null;
	valid_to: string | null;
	created_at: string;
	updated_at: string;
	rule_count: number;
	customer_count: number;
}

export interface PriceRule {
	id: number;
	pricebook_id: number;
	customer_id: number;
	product_id: number;
	variation_id: number;
	price_override: string | null;
	percent_off: string | null;
	min_qty: number;
	qty_step: number;
	qty_breaks: QtyBreak[];
	priority: number;
	valid_from: string | null;
	valid_to: string | null;
	created_at?: string;
	updated_at?: string;
}

export interface AssignedCustomer {
	id: number;
	display_name: string;
	email: string;
	billing_company: string;
}

export interface PriceBookDetail {
	book: PriceBook;
	rules: PriceRule[];
	customers: AssignedCustomer[];
}

export interface PriceResolution {
	unit_price: string;
	source: string;
	rule_id: number | null;
	min_qty: number;
	qty_step: number;
	explanation: string;
	warnings: string[];
	qty_breaks: QtyBreak[];
}

export type PacketStatus =
	| 'draft'
	| 'issued'
	| 'submitted'
	| 'in_review'
	| 'approved'
	| 'accepted'
	| 'ordered'
	| 'expired'
	| 'revoked'
	| 'cancelled'
	| 'archived';

export interface PacketSummary {
	id: number;
	ref: string;
	title: string;
	status: PacketStatus | string;
	customer_id: number;
	customer_name: string;
	customer_email: string;
	currency: string;
	pricebook_id: number | null;
	current_version_id: number | null;
	valid_until: string | null;
	created_at: string;
	updated_at: string;
}

export interface PacketLine {
	id: number;
	row_id: string;
	product_id: number;
	variation_id: number;
	sku: string;
	name: string;
	variation_summary: string;
	qty_min: number;
	qty_step: number;
	unit_price: string;
	price_source: string;
	qty_breaks: QtyBreak[];
	sort_order: number;
	image_id: number | null;
}

export interface PacketVersion {
	id: number;
	version_no: number;
	status: string;
	checksum: string;
	issued_at: string;
	expires_at: string | null;
	issued_by: number | null;
}

export interface PacketDocument {
	id: number;
	type: string;
	template: string;
	size_bytes: number;
	generated_at: string;
}

export interface PacketQuoteSummary {
	id: number;
	ref: string;
	revision_no: number;
	status: string;
	requested_at: string | null;
	expires_at: string | null;
	blocking_count: number;
	warning_count: number;
	converted_order_id: number | null;
}

export interface PacketTerms {
	payment_terms?: string;
	delivery_terms?: string;
	lead_time?: string;
	notes?: string;
	min_order_value?: string;
}

export interface PacketDetail {
	packet: PacketSummary;
	lines: PacketLine[];
	versions: PacketVersion[];
	current_version: {
		id: number;
		version_no: number;
		status: string;
		checksum: string;
		expires_at: string | null;
	} | null;
	documents: PacketDocument[];
	quotes: PacketQuoteSummary[];
	terms: PacketTerms;
}

export interface PreflightFinding {
	code: string;
	message: string;
	line_id?: number;
}

export interface Preflight {
	errors: PreflightFinding[];
	warnings: PreflightFinding[];
}

export interface PublishResult {
	version_id: number;
	version_no: number;
	checksum: string;
	token: string;
	share_url: string;
	warnings: PreflightFinding[];
	documents: PacketDocument[];
	template?: string;
	document_error?: string;
}

export interface ShareInfo {
	url: string;
	token: string;
	expires_at: string;
}

export interface AddLineResponse {
	line: PacketLine | null;
	resolution: PriceResolution | null;
}

export type QuoteStatus =
	| 'requested'
	| 'in_review'
	| 'revised'
	| 'approved'
	| 'accepted'
	| 'ordered'
	| 'rejected'
	| 'superseded'
	| 'expired'
	| 'cancelled';

export interface QuoteSummary {
	id: number;
	ref: string;
	packet_id: number;
	packet_ref: string;
	packet_title: string;
	version_id: number;
	revision_no: number;
	parent_quote_id: number | null;
	customer_id: number;
	customer_name: string;
	status: QuoteStatus | string;
	requested_at: string | null;
	reviewed_at: string | null;
	approved_at: string | null;
	accepted_at: string | null;
	expires_at: string | null;
	converted_order_id: number | null;
	notes_buyer: string;
	notes_merchant: string;
	blocking_count: number;
	warning_count: number;
}

export interface QuoteLine {
	id: number;
	packet_line_id: number;
	row_id: string;
	product_id: number;
	variation_id: number;
	sku: string;
	name: string;
	variation_summary: string;
	qty: number;
	unit_price: string;
	line_total: string;
	price_source: string;
	merchant_note: string | null;
}

export interface DiscrepancyItem {
	type: string;
	severity: 'blocking' | 'warning' | string;
	row_id: string;
	message: string;
}

export interface DiscrepancyReport {
	checked_at?: string;
	items: DiscrepancyItem[];
	blocking_count: number;
	warning_count: number;
	has_discrepancies: boolean;
}

export interface QuoteChange {
	row_id: string;
	name: string;
	change: 'price' | 'qty' | 'added' | 'removed' | string;
	old_price?: string;
	new_price?: string;
	old_qty?: number;
	new_qty?: number;
}

export interface QuoteDetail {
	quote: QuoteSummary;
	lines: QuoteLine[];
	total: string;
	report: Partial<DiscrepancyReport> | Record<string, never>;
	changes: QuoteChange[];
	packet: { id: number; ref: string; title: string; status: string } | null;
	version: { id: number; version_no: number; checksum: string } | null;
	order: { order_id: number } | null;
}

export interface ConvertResult {
	order_id?: number;
	pay_url?: string;
	message?: string;
	already?: boolean;
	pending?: boolean;
}

export interface QuoteRevisionChange {
	quote_line_id: number;
	unit_price?: string;
	qty?: number;
	remove?: boolean;
	note?: string;
}

export interface EventItem {
	id: number;
	object_type: string;
	object_id: number;
	action: string;
	actor_id: number | null;
	details: Record<string, unknown> | string;
	created_at: string;
}

export interface DashboardMetrics {
	draft: number;
	issued: number;
	submitted: number;
	in_review: number;
	approved: number;
	accepted: number;
	ordered: number;
	expired: number;
	revoked: number;
	cancelled: number;
	archived: number;
	awaiting: number;
	open_quotes: number;
	orders: number;
	failed_jobs: number;
	expiring: number;
}

export interface DashboardResponse {
	metrics: DashboardMetrics;
	events: EventItem[];
}

export interface Settings {
	company_name: string;
	company_address: string;
	company_email: string;
	company_phone: string;
	logo_id: number;
	doc_footer: string;
	default_validity_days: number;
	quote_validity_days: number;
	buyer_token_days: number;
	doc_size: string;
	default_template: string;
	file_retention_days: number;
	notify_emails: boolean;
	delete_data_on_uninstall: boolean;
}

export interface SettingsResponse {
	settings: Settings;
	currency: string;
	currency_symbol: string;
	templates: string[];
}

export interface DiagnosticCheck {
	name: string;
	label: string;
	ok: boolean;
	value: string;
	advice: string;
}

export interface DiagnosticsResponse {
	checks: DiagnosticCheck[];
	schema_version: string;
	plugin_version: string;
}
