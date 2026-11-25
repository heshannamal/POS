export type View = 'pos' | 'dashboard' | 'products' | 'sales' | 'reports' | 'settings';
export type SessionInfo = { authenticated: boolean; api_base: string; tenant_slug: string };
export type Profile = {
  tenant: { id: number; slug: string; name: string; currency: string; status: string };
  user: { id: number; name: string; email: string };
  device: { id: number; platform: string };
};
export type Branch = { id: number; name: string; code: string; is_default: boolean };
export type Category = { id: number; name: string; slug: string };
export type Product = {
  id: number; name: string; sku: string; barcode: string | null;
  category_id: number | null; price_minor: number; track_stock: boolean;
  quantity: number; updated_at: string | null;
};
export type Register = { id: number; branch_id: number; name: string };
export type OpenShift = {
  id: number; register_id: number; branch_id: number; opening_cash_minor: number;
  expected_cash_minor: number; opened_at: string;
};
export type Sale = {
  id: number; branch_id: number; shift_id: number | null;
  cashier_id: number; total_minor: number; subtotal_minor: number;
  discount_minor: number; currency: string; status: string;
  completed_at: string | null;
};
export type SaleItem = {
  id: number; product_id: number; name: string; sku: string;
  quantity: number; unit_price_minor: number; line_total_minor: number;
};
export type SaleDetail = Sale & {
  items: SaleItem[];
  payments: { method: string; amount_minor: number; reference: string | null }[];
};
export type CartItem = { product: Product; quantity: number };
export type HeldCart = { id: string; name: string; items: CartItem[]; discount_minor: number; branch_id: number };
export type PageMeta = { current_page: number; last_page: number; per_page: number; total: number };
export type CheckoutPayload = {
  branch_id: number; shift_id: number; idempotency_key: string;
  items: { product_id: number; quantity: number }[];
  discount_minor: number; payment_method: 'cash';
};
