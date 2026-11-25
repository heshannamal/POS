import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent } from 'react';
import {
  Activity, ArrowDownLeft, ArrowLeft, ArrowRight, BarChart3, Barcode, Bell, Box, Check,
  CheckCircle2, ChevronDown, CircleAlert, Clock3, Cloud, CreditCard, Grid2X2, Hold,
  LayoutDashboard, List, Loader2, LogOut, Menu, Minus, Moon, Package, Pause, Plus,
  Printer, Receipt, RefreshCw, Search, Settings, ShieldCheck, ShoppingBag, ShoppingCart,
  SlidersHorizontal, Store, Sun, Trash2, Wallet, Wifi, WifiOff, X
} from 'lucide-react';
import { friendlyError, login, logout, read, session, write } from './bridge';
import type {
  Branch, CartItem, Category, CheckoutPayload, HeldCart, OpenShift, PageMeta,
  Product, Profile, Register, Sale, SaleDetail, SessionInfo, View
} from './types';

type Envelope<T> = { data: T };
type ProductResponse = Envelope<Product[]> & { meta: PageMeta };
type RegistersResponse = Envelope<{ registers: Register[]; my_open_shifts: OpenShift[] }>;
type PaginatedSales = { data: Sale[]; current_page: number; total: number };
type ShiftMode = 'open' | 'cash_in' | 'cash_out' | 'close';
type Notice = { text: string; kind: 'success' | 'error' | 'info' };
type PendingCheckout = { payload: CheckoutPayload; total_minor: number };

const DEFAULT_API = 'https://pos.web7softwares.com/api/v1';

function money(value: number, currency: string): string {
  return currency + ' ' + (value / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function initials(name: string): string {
  return name.trim().split(/\s+/).slice(0, 2).map(word => word[0] ?? '').join('').toUpperCase();
}
function dateText(text: string | null | undefined): string {
  if (!text) return '—';
  const date = new Date(text);
  return Number.isNaN(date.getTime()) ? text : date.toLocaleString();
}
function App() {
  const [booting, setBooting] = useState(true);
  const [sessionInfo, setSessionInfo] = useState<SessionInfo | null>(null);
  const [profile, setProfile] = useState<Profile | null>(null);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [branchId, setBranchId] = useState<number | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [products, setProducts] = useState<Product[]>([]);
  const [pageMeta, setPageMeta] = useState<PageMeta>({ current_page: 1, last_page: 1, per_page: 50, total: 0 });
  const [page, setPage] = useState(1);
  const [searchText, setSearchText] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [catalogLoading, setCatalogLoading] = useState(false);
  const [coreBusy, setCoreBusy] = useState(false);
  const [connected, setConnected] = useState(false);
  const [refreshIndex, setRefreshIndex] = useState(0);
  const [registers, setRegisters] = useState<Register[]>([]);
  const [shifts, setShifts] = useState<OpenShift[]>([]);
  const [sales, setSales] = useState<Sale[]>([]);
  const [salesTotal, setSalesTotal] = useState(0);
  const [cart, setCart] = useState<CartItem[]>([]);
  const [held, setHeld] = useState<HeldCart[]>([]);
  const [discountText, setDiscountText] = useState('0');
  const [view, setView] = useState<View>('pos');
  const [listMode, setListMode] = useState(false);
  const [dark, setDark] = useState(false);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [shiftMode, setShiftMode] = useState<ShiftMode | null>(null);
  const [selectedRegisterId, setSelectedRegisterId] = useState<number | null>(null);
  const [shiftCash, setShiftCash] = useState('');
  const [shiftNote, setShiftNote] = useState('');
  const [shiftBusy, setShiftBusy] = useState(false);
  const [checkoutOpen, setCheckoutOpen] = useState(false);
  const [cashTendered, setCashTendered] = useState('');
  const [checkoutBusy, setCheckoutBusy] = useState(false);
  const [pendingCheckout, setPendingCheckout] = useState<PendingCheckout | null>(null);
  const [receipt, setReceipt] = useState<SaleDetail | null>(null);
  const [sideOpen, setSideOpen] = useState(false);
  const searchRef = useRef<HTMLInputElement>(null);

  const slug = profile?.tenant.slug ?? sessionInfo?.tenant_slug ?? '';
  const currency = profile?.tenant.currency ?? 'LKR';
  const currentBranch = branches.find(branch => branch.id === branchId) ?? null;
  const branchRegisters = registers.filter(register => register.branch_id === branchId);
  const activeShift = shifts.find(shift => shift.branch_id === branchId) ?? null;
  const subtotal = cart.reduce((sum, item) => sum + item.product.price_minor * item.quantity, 0);
  const parsedDiscount = Number(discountText);
  const discount = Number.isFinite(parsedDiscount) && parsedDiscount >= 0
    ? Math.min(subtotal, Math.round(parsedDiscount * 100)) : 0;
  const total = subtotal - discount;
  const cartCount = cart.reduce((sum, item) => sum + item.quantity, 0);
  const tenderedMinor = Math.max(0, Math.round((Number(cashTendered) || 0) * 100));
  const changeMinor = Math.max(0, tenderedMinor - total);

  const notify = useCallback((text: string, kind: Notice['kind'] = 'info') => {
    setNotice({ text, kind });
  }, []);

  const loadProfile = useCallback(async (tenantSlug: string) => {
    const [account, branchResult] = await Promise.all([
      read<Envelope<Profile>>(tenantSlug, '/me'),
      read<Envelope<Branch[]>>(tenantSlug, '/branches')
    ]);
    setProfile(account.data);
    setBranches(branchResult.data);
    setBranchId(previous => branchResult.data.some(b => b.id === previous)
      ? previous : branchResult.data.find(b => b.is_default)?.id ?? branchResult.data[0]?.id ?? null);
    setConnected(true);
  }, []);

  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        const existing = await session();
        if (!alive) return;
        setSessionInfo(existing);
        if (existing.authenticated) await loadProfile(existing.tenant_slug);
      } catch (error) {
        if (alive) notify(friendlyError(error), 'error');
      } finally {
        if (alive) setBooting(false);
      }
    })();
    return () => { alive = false; };
  }, [loadProfile, notify]);

  useEffect(() => {
    const timer = setTimeout(() => { setSearchTerm(searchText.trim()); setPage(1); }, 260);
    return () => clearTimeout(timer);
  }, [searchText]);

  useEffect(() => {
    if (!profile || !branchId) return;
    let alive = true;
    setCatalogLoading(true);
    const qs = new URLSearchParams({ branch_id: String(branchId), page: String(page), per_page: '50' });
    if (searchTerm) qs.set('search', searchTerm);
    if (categoryId) qs.set('category_id', String(categoryId));
    read<ProductResponse>(profile.tenant.slug, '/products?' + qs)
      .then(result => {
        if (!alive) return;
        setProducts(result.data);
        setPageMeta(result.meta);
        setConnected(true);
      })
      .catch(error => { if (alive) { setConnected(false); notify(friendlyError(error), 'error'); } })
      .finally(() => { if (alive) setCatalogLoading(false); });
    return () => { alive = false; };
  }, [profile, branchId, page, categoryId, searchTerm, refreshIndex, notify]);

  const refreshOperations = useCallback(async () => {
    if (!profile) return;
    const currentSlug = profile.tenant.slug;
    const results = await Promise.allSettled([
      read<RegistersResponse>(currentSlug, '/registers'),
      read<Envelope<OpenShift[]>>(currentSlug, '/shifts'),
      read<Envelope<Category[]>>(currentSlug, '/categories'),
      read<PaginatedSales>(currentSlug, '/sales?per_page=50')
    ]);
    if (results[0].status === 'fulfilled') {
      setRegisters(results[0].value.data.registers);
    }
    if (results[1].status === 'fulfilled') setShifts(results[1].value.data);
    if (results[2].status === 'fulfilled') setCategories(results[2].value.data);
    if (results[3].status === 'fulfilled') {
      setSales(results[3].value.data);
      setSalesTotal(results[3].value.total);
    }
  }, [profile]);

  useEffect(() => {
    if (!profile || !branchId) return;
    void refreshOperations();
  }, [profile, branchId, refreshIndex, refreshOperations]);

  const setBranch = (id: number) => {
    if (id === branchId) return;
    if (cart.length > 0 && !window.confirm('Switching branches will clear the active cart. Continue?')) return;
    setCart([]);
    setPendingCheckout(null);
    setDiscountText('0');
    setCategoryId(null);
    setPage(1);
    setBranchId(id);
  };

  const addProduct = (product: Product) => {
    if (pendingCheckout) return notify('Resolve the pending payment attempt before changing this cart.', 'error');
    const existing = cart.find(line => line.product.id === product.id);
    const nextQuantity = (existing?.quantity ?? 0) + 1;
    if (product.track_stock && nextQuantity > product.quantity) {
      return notify('Insufficient stock for ' + product.name, 'error');
    }
    setCart(previous => existing
      ? previous.map(line => line.product.id === product.id ? { ...line, quantity: nextQuantity } : line)
      : [...previous, { product, quantity: 1 }]);
  };

  const changeQuantity = (productId: number, delta: number) => {
    if (pendingCheckout) return notify('Resolve the pending payment attempt first.', 'error');
    setCart(previous => previous.flatMap(line => {
      if (line.product.id !== productId) return [line];
      const next = line.quantity + delta;
      if (next <= 0) return [];
      if (line.product.track_stock && next > line.product.quantity) {
        notify('Not enough stock to increase quantity.', 'error');
        return [line];
      }
      return [{ ...line, quantity: next }];
    }));
  };

  const clearCart = () => {
    if (pendingCheckout) return notify('Confirm the pending sale status before clearing the cart.', 'error');
    if (cart.length && !window.confirm('Remove every item from the current sale?')) return;
    setCart([]);
    setDiscountText('0');
  };

  const holdCart = () => {
    if (pendingCheckout) return notify('Resolve the pending sale first.', 'error');
    if (!cart.length || !branchId) return;
    setHeld(previous => [{ id: crypto.randomUUID(), branch_id: branchId,
      items: cart, discount_minor: discount,
      name: 'Held ' + new Date().toLocaleTimeString() }, ...previous].slice(0, 12));
    setCart([]);
    setDiscountText('0');
    notify('Sale held in this running app only. It will not survive a restart.', 'info');
  };

  const resumeHeld = (id: string) => {
    if (cart.length > 0 || pendingCheckout) return notify('Finish or clear the current cart before resuming.', 'error');
    const item = held.find(h => h.id === id);
    if (!item) return;
    if (item.branch_id !== branchId) return notify('Select the original branch to resume this sale.', 'error');
    setCart(item.items);
    setDiscountText((item.discount_minor / 100).toFixed(2));
    setHeld(previous => previous.filter(h => h.id !== id));
    setView('pos');
  };

  const onScanKey = async (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    const code = searchText.trim();
    if (!code || !branchId) return;
    try {
      const qs = new URLSearchParams({ branch_id: String(branchId), search: code, per_page: '20' });
      const found = await read<ProductResponse>(slug, '/products?' + qs);
      const match = found.data.find(product => product.barcode === code || product.sku.toLowerCase() === code.toLowerCase());
      if (match) {
        addProduct(match);
        setSearchText('');
      } else {
        setSearchTerm(code);
        notify('Showing matches for: ' + code);
      }
    } catch (error) { notify(friendlyError(error), 'error'); }
  };

  const refreshAll = async () => {
    setCoreBusy(true);
    try {
      await refreshOperations();
      setRefreshIndex(value => value + 1);
      setConnected(true);
      notify('Products, stock and cashier data refreshed.', 'success');
    } catch (error) {
      setConnected(false);
      notify(friendlyError(error), 'error');
    } finally { setCoreBusy(false); }
  };

  const openShiftModal = (mode: ShiftMode) => {
    setShiftMode(mode);
    setShiftCash(mode === 'close' && activeShift ? (activeShift.expected_cash_minor / 100).toFixed(2) : '');
    setShiftNote('');
    setSelectedRegisterId(branchRegisters[0]?.id ?? null);
  };

  const submitShift = async (event: FormEvent) => {
    event.preventDefault();
    if (!profile) return;
    setShiftBusy(true);
    try {
      if (shiftMode === 'open') {
        if (!selectedRegisterId) throw new Error('Choose an active register.');
        await write(profile.tenant.slug, '/shifts', {
          register_id: selectedRegisterId, opening_cash: shiftCash
        });
      } else if (activeShift && shiftMode === 'close') {
        await write(profile.tenant.slug, '/shifts/' + activeShift.id + '/close', {
          closing_cash: shiftCash, note: shiftNote || null
        });
      } else if (activeShift && (shiftMode === 'cash_in' || shiftMode === 'cash_out')) {
        await write(profile.tenant.slug, '/shifts/' + activeShift.id + '/cash-movements', {
          type: shiftMode, amount: shiftCash, reason: shiftNote
        });
      } else { throw new Error('No active shift selected.'); }
      setShiftMode(null);
      await refreshOperations();
      notify('Cashier shift updated successfully.', 'success');
    } catch (error) {
      notify(friendlyError(error), 'error');
    } finally { setShiftBusy(false); }
  };

  const checkout = async () => {
    if (!profile || !branchId || !activeShift || !cart.length) return;
    if (tenderedMinor < total) return notify('Cash received must cover the total.', 'error');
    const candidate: PendingCheckout = pendingCheckout ?? {
      payload: {
        branch_id: branchId, shift_id: activeShift.id, idempotency_key: crypto.randomUUID(),
        items: cart.map(line => ({ product_id: line.product.id, quantity: line.quantity })),
        discount_minor: discount, payment_method: 'cash'
      },
      total_minor: total
    };
    setPendingCheckout(candidate);
    setCheckoutBusy(true);
    try {
      const result = await write<Envelope<SaleDetail>>(profile.tenant.slug, '/sales', candidate.payload);
      setReceipt(result.data);
      setCart([]);
      setDiscountText('0');
      setPendingCheckout(null);
      setCheckoutOpen(false);
      setCashTendered('');
      await refreshOperations();
      setRefreshIndex(value => value + 1);
      notify('Sale #' + result.data.id + ' completed successfully.', 'success');
    } catch (error) {
      notify('Sale not confirmed: ' + friendlyError(error) + '. Retry the same transaction key after checking connectivity.', 'error');
    } finally { setCheckoutBusy(false); }
  };

  const openSale = async (id: number) => {
    try {
      const detail = await read<Envelope<SaleDetail>>(slug, '/sales/' + id);
      setReceipt(detail.data);
    } catch (error) { notify(friendlyError(error), 'error'); }
  };

  const doLogout = async () => {
    if (pendingCheckout) return notify('Resolve the pending payment attempt before signing out.', 'error');
    if (cart.length > 0 && !window.confirm('Sign out and discard the current cart?')) return;
    try {
      await logout();
      setProfile(null);
      setBranches([]);
      setProducts([]);
      setCart([]);
      setHeld([]);
      setShifts([]);
      setSessionInfo(await session());
      setView('pos');
    } catch (error) { notify(friendlyError(error), 'error'); }
  };

  useEffect(() => {
    const handler = (event: globalThis.KeyboardEvent) => {
      if (event.key === 'F2' || (event.ctrlKey && event.key.toLowerCase() === 'f')) {
        event.preventDefault(); searchRef.current?.focus(); setView('pos');
      }
      if (event.key === 'F8') { event.preventDefault(); holdCart(); }
      if (event.key === 'F10') { event.preventDefault(); clearCart(); }
      if (event.key === 'F12') {
        event.preventDefault();
        if (cart.length && activeShift) {
          setCashTendered((total / 100).toFixed(2));
          setCheckoutOpen(true);
        }
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  });

  const recentBranchSales = sales.filter(sale => sale.branch_id === branchId);
  const recentRevenue = useMemo(() => recentBranchSales
    .filter(sale => sale.status === 'completed')
    .reduce((value, sale) => value + sale.total_minor, 0), [recentBranchSales]);
  const navItems: { id: View; label: string; icon: typeof LayoutDashboard }[] = [
    { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { id: 'pos', label: 'Point of Sale', icon: ShoppingCart },
    { id: 'products', label: 'Products', icon: Box },
    { id: 'sales', label: 'Sales History', icon: BarChart3 },
    { id: 'reports', label: 'Reports', icon: Activity },
    { id: 'settings', label: 'Settings', icon: Settings }
  ];

  if (booting) return <div className="boot-screen"><Loader2 className="spin" size={40}/><strong>Loading POS Cloud Desktop</strong></div>;

  if (!profile) {
    return <LoginScreen
      defaultBase={sessionInfo?.api_base ?? DEFAULT_API}
      defaultSlug={sessionInfo?.tenant_slug ?? ''}
      onLoggedIn={async () => {
        const existing = await session();
        setSessionInfo(existing);
        await loadProfile(existing.tenant_slug);
      }}
      notice={notice}
      onNotice={notify}
    />;
  }

  return <div className={'app-shell' + (dark ? ' dark' : '')}>
    <aside className={'sidebar' + (sideOpen ? ' sidebar-open' : '')}>
      <div className="brand">
        <div className="brand-icon"><ShoppingBag size={22} strokeWidth={2.5}/></div>
        <div><strong>POS Cloud</strong><span>Desktop Register <b>PRO</b></span></div>
      </div>
      <p className="nav-section-label">WORKSPACE</p>
      <nav className="nav-list" aria-label="Desktop navigation">
        {navItems.map(item => {
          const Icon = item.icon;
          return <button key={item.id} className={'nav-link' + (view === item.id ? ' active' : '')}
            onClick={() => { setView(item.id); setSideOpen(false); }}>
            <Icon size={20}/><span>{item.label}</span>{view === item.id && <span className="active-dot"/>}
          </button>;
        })}
        <div className="nav-link disabled" title="Customer loyalty requires Phase 11">
          <ShieldCheck size={20}/><span>Customers</span><small>Soon</small>
        </div>
      </nav>
      <div className="sidebar-footer">
        <div className="store-mini"><div className="store-avatar"><Store size={24}/></div>
          <strong>{profile.tenant.name}</strong><span>{currentBranch?.name ?? 'No branch selected'}</span>
        </div>
        <div className="sidebar-foot-actions">
          <button aria-label="Toggle appearance" onClick={() => setDark(value => !value)}>{dark ? <Sun size={18}/> : <Moon size={18}/>}</button>
          <button aria-label="Sign out" onClick={() => void doLogout()}><LogOut size={18}/></button>
        </div>
      </div>
    </aside>

    <div className="app-main">
      <header className="topbar">
        <button className="mobile-only subtle-icon" onClick={() => setSideOpen(value => !value)}><Menu size={22}/></button>
        <div className="top-store"><div className="store-emoji"><Store size={23}/></div>
          <div><strong>{profile.tenant.name}</strong><small>Retail workspace</small></div>
        </div>
        <div className="top-divider"/>
        <label className="branch-picker"><small>Branch</small>
          <select value={branchId ?? ''} onChange={event => setBranch(Number(event.target.value))}
            aria-label="Choose branch">
            {branches.map(branch => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
          </select>
        </label>
        <div className="top-search">
          <Search size={19}/>
          <input ref={searchRef} placeholder="Search products by name, SKU, or barcode..."
            value={searchText} onChange={event => { setSearchText(event.target.value); setView('pos'); }}
            onKeyDown={event => void onScanKey(event)} aria-label="Search products"/>
          <Barcode size={22} className="scan-indicator"/>
        </div>
        <button className="top-refresh" onClick={() => void refreshAll()} title="Refresh from server">
          <RefreshCw size={19} className={coreBusy ? 'spin' : ''}/>
        </button>
        <div className="cashier"><div className="cashier-avatar">{initials(profile.user.name)}</div>
          <div><small>Cashier</small><strong>{profile.user.name}</strong><span className={connected ? 'online' : 'offline'}><i/>{connected ? 'Connected' : 'Connection issue'}</span></div>
        </div>
      </header>

      {notice && <div className={'toast ' + notice.kind} role="alert"><span>{notice.text}</span>
        <button onClick={() => setNotice(null)} aria-label="Dismiss"><X size={17}/></button></div>}

      <div className="content">
        {view === 'pos' && <div className="pos-layout">
          <div className="catalog">
            <div className="page-heading">
              <div className="heading-symbol"><ShoppingCart size={22}/></div>
              <div><p className="eyebrow">CASHIER WORKSPACE</p><h1>Point of Sale</h1></div>
              <span className="heading-trail">Register {activeShift ? '#' + activeShift.register_id : 'closed'}</span>
            </div>
            <div className="category-bar">
              <button className={'category-chip' + (!categoryId ? ' chosen' : '')}
                onClick={() => { setCategoryId(null); setPage(1); }}><Grid2X2 size={16}/> All Products</button>
              {categories.map(category => <button key={category.id}
                className={'category-chip' + (categoryId === category.id ? ' chosen' : '')}
                onClick={() => { setCategoryId(category.id); setPage(1); }}>{category.name}</button>)}
            </div>
            <div className="catalog-toolbar">
              <div><h2>{categories.find(c => c.id === categoryId)?.name ?? 'All Products'}</h2>
                <span>{pageMeta.total.toLocaleString()} available products</span></div>
              <div className="catalog-toolbar-actions">
                <button className={'view-switch' + (!listMode ? 'selected' : '')}
                  onClick={() => setListMode(false)} aria-label="Grid view"><Grid2X2 size={18}/></button>
                <button className={'view-switch' + (listMode ? 'selected' : '')}
                  onClick={() => setListMode(true)} aria-label="List view"><List size={19}/></button>
              </div>
            </div>
            {catalogLoading && <div className="catalog-loading"><Loader2 size={19} className="spin"/> Loading live products...</div>}
            {!catalogLoading && products.length === 0 && <div className="empty-state"><Package size={44}/><h3>No matching products</h3><p>Search by product, SKU or barcode, or choose another category.</p></div>}
            <div className={'product-grid' + (listMode ? ' products-list' : '')}>
              {products.map((product, index) => {
                const out = product.track_stock && product.quantity < 1;
                return <article className={'product-card' + (out ? ' sold-out' : '')} key={product.id}>
                  <div className={'product-art art-' + (index % 6)}><Package size={42} strokeWidth={1.45}/></div>
                  <div className="product-body">
                    <div className="product-sku">{product.sku}</div>
                    <h3 title={product.name}>{product.name}</h3>
                    <strong className="product-price">{money(product.price_minor, currency)}</strong>
                    <div className="product-foot">
                      <span className={'stock-label' + (out ? ' out' : '')}>{product.track_stock ? (out ? 'Out of stock' : 'In stock · ' + product.quantity) : 'Available'}</span>
                      <button className="add-product" disabled={out || Boolean(pendingCheckout)}
                        onClick={() => addProduct(product)} aria-label={'Add ' + product.name}><Plus size={17}/> Add</button>
                    </div>
                  </div>
                </article>;
              })}
            </div>
            {pageMeta.last_page > 1 && <div className="pagination">
              <button disabled={page <= 1} onClick={() => setPage(page - 1)}><ArrowLeft size={16}/> Previous</button>
              <span>Page {pageMeta.current_page} of {pageMeta.last_page}</span>
              <button disabled={page >= pageMeta.last_page} onClick={() => setPage(page + 1)}>Next <ArrowRight size={16}/></button>
            </div>}
          </div>

          <aside className="cart-panel">
            <div className="cart-heading"><div><span className="cart-icon"><ShoppingCart size={22}/></span>
              <h2>Current Sale</h2></div><span>{cartCount} {cartCount === 1 ? 'item' : 'items'}</span></div>
            {!activeShift && <button className="shift-warning" onClick={() => openShiftModal('open')}>
              <CircleAlert size={19}/><span><strong>Shift not open</strong><small>Open a register to accept cash payments</small></span><ArrowRight size={16}/></button>}
            <div className="cart-scroll">
              {cart.length === 0 ? <div className="cart-empty"><ShoppingBag size={45}/>
                <h3>Your cart is empty</h3><p>Select a product to start the next sale.</p></div>
              : cart.map(line => <div className="cart-item" key={line.product.id}>
                <div className="cart-art"><Package size={23}/></div>
                <div className="cart-item-info"><strong>{line.product.name}</strong><small>{line.product.sku}</small>
                  <b>{money(line.product.price_minor, currency)}</b></div>
                <div className="cart-quantity">
                  <button onClick={() => changeQuantity(line.product.id, -1)} disabled={Boolean(pendingCheckout)} aria-label="Reduce quantity"><Minus size={14}/></button>
                  <strong>{line.quantity}</strong>
                  <button onClick={() => changeQuantity(line.product.id, 1)} disabled={Boolean(pendingCheckout)} aria-label="Increase quantity"><Plus size={14}/></button>
                </div>
                <div className="cart-line-total">{money(line.product.price_minor * line.quantity, currency)}</div>
                <button className="cart-remove" disabled={Boolean(pendingCheckout)}
                  onClick={() => changeQuantity(line.product.id, -line.quantity)} aria-label="Remove product"><Trash2 size={17}/></button>
              </div>)}
            </div>
            <div className="totals">
              <div className="total-row"><span>Subtotal</span><strong>{money(subtotal, currency)}</strong></div>
              <div className="total-row"><label htmlFor="discount-value">Discount</label>
                <div className="discount-control"><span>{currency}</span><input id="discount-value" type="number"
                  value={discountText} min="0" max={(subtotal / 100).toFixed(2)} step=".01"
                  disabled={Boolean(pendingCheckout)} onChange={event => setDiscountText(event.target.value)}/></div></div>
              <div className="total-row muted-row"><span>Tax</span><span>Not configured</span></div>
              <div className="grand-total"><strong>Total</strong><b>{money(total, currency)}</b></div>
            </div>
            <div className="cart-actions">
              <button className="pay-button" disabled={!cart.length || !activeShift}
                onClick={() => { setCashTendered((total / 100).toFixed(2)); setCheckoutOpen(true); }}>
                <Wallet size={24}/> Pay Cash <kbd>F12</kbd></button>
              <div className="cart-secondary">
                <button onClick={holdCart} disabled={!cart.length || Boolean(pendingCheckout)}><Pause size={18}/> Hold Sale <kbd>F8</kbd></button>
                <button className="clear-button" onClick={clearCart} disabled={!cart.length || Boolean(pendingCheckout)}><Trash2 size={18}/> Clear <kbd>F10</kbd></button>
              </div>
            </div>
            {held.filter(h => h.branch_id === branchId).length > 0 && <div className="held-sales">
              <h3><Hold size={16}/> Held sales · This session only</h3>
              {held.filter(h => h.branch_id === branchId).map(h => <button key={h.id} onClick={() => resumeHeld(h.id)}>
                {h.name} <span>Resume <ArrowRight size={14}/></span></button>)}
            </div>}
          </aside>
        </div>}

        {view === 'dashboard' && <div className="work-page">
          <div className="section-heading"><p className="eyebrow">TODAY'S WORKSPACE</p><h1>Business overview</h1><p>Live shift information and a snapshot of recently loaded sales.</p></div>
          <div className="metric-grid">
            <Metric icon={<Store/>} label="Selected branch" value={currentBranch?.name ?? 'No branch'}/>
            <Metric icon={<Clock3/>} label="Cashier shift" value={activeShift ? '#' + activeShift.id + ' Open' : 'Not open'}/>
            <Metric icon={<Wallet/>} label="Expected register cash" value={activeShift ? money(activeShift.expected_cash_minor, currency) : '—'}/>
            <Metric icon={<Receipt/>} label="Recent sales loaded" value={String(recentBranchSales.length)}/>
          </div>
          <section className="screen-card">
            <div className="screen-card-head"><div><h2>Register controls</h2><p>Open, reconcile, and monitor your current cashier shift.</p></div>
              <button className="outline-action" onClick={() => void refreshAll()}><RefreshCw size={16}/> Refresh</button></div>
            <div className="register-summary"><div className="register-circle"><Wallet size={26}/></div>
              <div><strong>{activeShift ? 'Shift #' + activeShift.id : 'No open shift'}</strong>
                <p>{activeShift ? 'Opened ' + dateText(activeShift.opened_at) : 'Select a register to begin selling'}</p></div>
              {activeShift ? <div className="register-buttons">
                <button onClick={() => openShiftModal('cash_in')}><Plus size={15}/> Cash in</button>
                <button onClick={() => openShiftModal('cash_out')}><Minus size={15}/> Cash out</button>
                <button className="primary-small" onClick={() => openShiftModal('close')}>Close shift</button>
              </div> : <button className="primary-small" onClick={() => openShiftModal('open')}>Open register</button>}
            </div>
          </section>
          <section className="screen-card"><div className="screen-card-head"><h2>Latest sales</h2>
            <button className="outline-action" onClick={() => setView('sales')}>View history <ArrowRight size={16}/></button></div>
            <SalesTable sales={recentBranchSales.slice(0, 8)} currency={currency} onOpen={id => void openSale(id)}/></section>
        </div>}

        {view === 'products' && <div className="work-page"><div className="section-heading">
          <p className="eyebrow">LIVE STORE INVENTORY</p><h1>Products & Stock</h1>
          <p>Displaying the current catalog page for {currentBranch?.name ?? 'your store'}. Search from the top bar to find any product.</p></div>
          <section className="screen-card"><div className="screen-card-head"><h2>Products ({pageMeta.total})</h2>
            <button className="outline-action" onClick={() => void refreshAll()}><RefreshCw size={17}/> Refresh</button></div>
            <div className="table-overflow"><table className="data-table"><thead><tr><th>Product</th><th>SKU</th><th>Barcode</th><th>Price</th><th>Available</th></tr></thead>
            <tbody>{products.map(product => <tr key={product.id}><td><strong>{product.name}</strong></td><td>{product.sku}</td><td>{product.barcode ?? '—'}</td>
              <td>{money(product.price_minor, currency)}</td><td>{product.track_stock ? product.quantity : 'Untracked'}</td></tr>)}</tbody></table></div>
          </section>
        </div>}

        {view === 'sales' && <div className="work-page"><div className="section-heading"><p className="eyebrow">TRANSACTIONS</p><h1>Sales History</h1>
          <p>Recent transactions for assigned branches. Open a sale to inspect or print its receipt.</p></div>
          <section className="screen-card"><div className="screen-card-head"><h2>Recent sales · {salesTotal} total</h2>
            <button className="outline-action" onClick={() => void refreshAll()}><RefreshCw size={16}/> Refresh</button></div>
            <SalesTable sales={sales} currency={currency} onOpen={id => void openSale(id)}/></section>
        </div>}

        {view === 'reports' && <div className="work-page"><div className="section-heading"><p className="eyebrow">CASHIER INSIGHTS</p><h1>Reports</h1>
          <p>This is a snapshot of the most recent 50 sales loaded from the server, not an all-time accounting report.</p></div>
          <div className="metric-grid">
            <Metric icon={<Receipt/>} label="Loaded sales, all branches" value={String(sales.length)}/>
            <Metric icon={<BarChart3/>} label="Selected branch: loaded sales" value={String(recentBranchSales.length)}/>
            <Metric icon={<Wallet/>} label="Selected branch: loaded revenue" value={money(recentRevenue, currency)}/>
            <Metric icon={<Package/>} label="Catalog matches" value={String(pageMeta.total)}/>
          </div>
          <section className="screen-card"><div className="screen-card-head"><h2>Selected branch transactions</h2>
            <button className="outline-action" onClick={() => setView('sales')}>All recent sales <ArrowRight size={16}/></button></div>
            <SalesTable sales={recentBranchSales} currency={currency} onOpen={id => void openSale(id)}/></section>
        </div>}

        {view === 'settings' && <div className="work-page"><div className="section-heading"><p className="eyebrow">DEVICE & SESSION</p><h1>Settings</h1>
          <p>Manage your local desktop workspace and securely sign out.</p></div>
          <section className="screen-card settings-card"><h2>Connection</h2><div className="settings-row"><Cloud size={21}/>
            <div><strong>Laravel API endpoint</strong><span>{sessionInfo?.api_base}</span></div><span className="status-chip"><CheckCircle2 size={14}/> Online only</span></div>
            <div className="settings-row"><ShieldCheck size={21}/><div><strong>Business</strong><span>{profile.tenant.name} · {profile.tenant.slug}</span></div></div>
            <div className="settings-row"><CreditCard size={21}/><div><strong>Payments</strong><span>Cash only · server-verified checkout</span></div></div>
            <div className="settings-row"><WifiOff size={21}/><div><strong>Offline transactions</strong><span>Disabled until conflict resolution and testing are complete.</span></div></div>
          </section>
          <section className="screen-card settings-card"><div className="screen-card-head"><h2>Appearance</h2></div>
            <button className="outline-action" onClick={() => setDark(value => !value)}>{dark ? <Sun size={17}/> : <Moon size={17}/>}
              Switch to {dark ? 'light' : 'dark'} mode</button></section>
          <button className="signout-button" onClick={() => void doLogout()}><LogOut size={18}/> Sign out of this device</button>
        </div>}
      </div>

      <footer className="statusbar">
        <div><Barcode size={19}/><span className={'status-light ' + (connected ? 'green' : 'red')}/>
          Barcode input <b>{connected ? 'Ready' : 'Connection issue'}</b></div>
        <span className="status-sep"/>
        <span><kbd>F2</kbd> Search</span><span className="status-sep"/>
        <span><kbd>F8</kbd> Hold</span><span className="status-sep"/>
        <span><kbd>F10</kbd> Clear</span><span className="status-sep"/>
        <span><kbd>F12</kbd> Pay Cash</span>
        <div className="status-end">{connected ? <Wifi size={16}/> : <WifiOff size={16}/>}
          {connected ? 'Connected to POS Cloud' : 'Server connection needs attention'}</div>
      </footer>
    </div>

    {shiftMode && <div className="modal-backdrop" onMouseDown={event => { if (event.target === event.currentTarget && !shiftBusy) setShiftMode(null); }}>
      <form className="dialog" onSubmit={event => void submitShift(event)}>
        <div className="dialog-heading"><span className="dialog-icon"><Wallet size={23}/></span>
          <div><h2>{shiftMode === 'open' ? 'Open Cash Register' : shiftMode === 'close' ? 'Close & Reconcile Shift' : shiftMode === 'cash_in' ? 'Cash In' : 'Cash Out'}</h2>
            <p>{currentBranch?.name} · {profile.tenant.name}</p></div>
          <button type="button" className="subtle-icon" onClick={() => setShiftMode(null)}><X size={20}/></button></div>
        {shiftMode === 'open' ? <label className="field">Register
          <select required value={selectedRegisterId ?? ''} onChange={event => setSelectedRegisterId(Number(event.target.value))}>
            <option value="">Choose a register</option>{branchRegisters.map(register => <option key={register.id} value={register.id}>{register.name}</option>)}
          </select></label> : <p className="dialog-context">Shift #{activeShift?.id} · Expected cash: {money(activeShift?.expected_cash_minor ?? 0, currency)}</p>}
        <label className="field">{shiftMode === 'open' ? 'Opening cash' : shiftMode === 'close' ? 'Counted cash' : 'Amount'} ({currency})
          <input required type="number" step=".01" min={shiftMode === 'cash_in' || shiftMode === 'cash_out' ? '.01' : '0'}
            value={shiftCash} onChange={event => setShiftCash(event.target.value)} placeholder="0.00"/></label>
        {shiftMode !== 'open' && <label className="field">{shiftMode === 'close' ? 'Closing note (optional)' : 'Reason'}
          <textarea value={shiftNote} required={shiftMode !== 'close'} maxLength={shiftMode === 'close' ? 3000 : 500}
            onChange={event => setShiftNote(event.target.value)} placeholder="Enter a note"/></label>}
        <div className="dialog-actions"><button className="outline-action" type="button" onClick={() => setShiftMode(null)}>Cancel</button>
          <button className="primary-small" disabled={shiftBusy || (shiftMode === 'open' && !selectedRegisterId)}>
            {shiftBusy ? <Loader2 className="spin" size={16}/> : <Check size={16}/>} Confirm</button></div>
      </form>
    </div>}

    {checkoutOpen && <div className="modal-backdrop" onMouseDown={event => { if (event.target === event.currentTarget && !checkoutBusy) setCheckoutOpen(false); }}>
      <div className="dialog checkout-dialog">
        <div className="dialog-heading"><span className="dialog-icon green-icon"><Wallet size={23}/></span>
          <div><h2>Cash Payment</h2><p>Register #{activeShift?.register_id} · {currentBranch?.name}</p></div>
          <button className="subtle-icon" onClick={() => setCheckoutOpen(false)} disabled={checkoutBusy}><X size={20}/></button></div>
        <div className="checkout-total"><span>Amount due</span><strong>{money(pendingCheckout?.total_minor ?? total, currency)}</strong></div>
        <label className="field">Cash received ({currency})<input type="number" step=".01" min="0" value={cashTendered}
          disabled={checkoutBusy} onChange={event => setCashTendered(event.target.value)} autoFocus/></label>
        <div className="change-row"><span>Change to give</span><strong>{money(changeMinor, currency)}</strong></div>
        {pendingCheckout && <p className="pending-warning"><CircleAlert size={16}/> A prior attempt may have reached the server. Retry with the same transaction ID; do not start a new payment until checking sales history.</p>}
        <button className="pay-button" disabled={checkoutBusy || tenderedMinor < (pendingCheckout?.total_minor ?? total)}
          onClick={() => void checkout()}>{checkoutBusy ? <Loader2 className="spin" size={20}/> : <CheckCircle2 size={22}/>}
          {pendingCheckout ? 'Retry same payment' : 'Confirm cash sale'}</button>
        {pendingCheckout && <button className="outline-action full" onClick={() => {
          if (window.confirm('Check Sales History to confirm this transaction was NOT completed before discarding the retry key. Continue?')) {
            setPendingCheckout(null);
            setCheckoutOpen(false);
          }
        }}>Discard uncertain attempt (after verifying history)</button>}
      </div>
    </div>}

    {receipt && <div className="modal-backdrop receipt-overlay" onMouseDown={event => { if (event.target === event.currentTarget) setReceipt(null); }}>
      <div className="receipt-dialog"><div className="receipt-screen-actions">
        <strong><Receipt size={18}/> Sale #{receipt.id}</strong>
        <div><button onClick={() => window.print()} className="outline-action"><Printer size={16}/> Print</button>
          <button className="subtle-icon" onClick={() => setReceipt(null)}><X size={19}/></button></div></div>
        <div className="print-receipt">
          <h2>{profile.tenant.name}</h2><p>{currentBranch?.name ?? 'POS Cloud Store'}</p>
          <p className="receipt-rule">SALE RECEIPT #{receipt.id}<br/>{dateText(receipt.completed_at)}</p>
          <div className="receipt-lines">{receipt.items.map(item => <div key={item.id}>
            <span>{item.name}<small>{item.quantity} × {money(item.unit_price_minor, currency)}</small></span>
            <b>{money(item.line_total_minor, currency)}</b></div>)}</div>
          <div className="receipt-rule"><div className="receipt-sum"><span>Subtotal</span><b>{money(receipt.subtotal_minor, currency)}</b></div>
            <div className="receipt-sum"><span>Discount</span><b>− {money(receipt.discount_minor, currency)}</b></div>
            <div className="receipt-sum receipt-strong"><span>TOTAL</span><b>{money(receipt.total_minor, currency)}</b></div></div>
          <p className="receipt-thanks">Thank you for shopping with us!<br/><small>Powered by POS Cloud</small></p>
        </div>
      </div>
    </div>}
  </div>;
}

function Metric({ icon, label, value }: { icon: React.ReactNode; label: string; value: string }) {
  return <div className="metric-card"><span className="metric-icon">{icon}</span><p>{label}</p><strong>{value}</strong></div>;
}

function SalesTable({ sales, currency, onOpen }: { sales: Sale[]; currency: string; onOpen: (id: number) => void }) {
  return <div className="table-overflow"><table className="data-table"><thead><tr>
    <th>Sale #</th><th>Date & Time</th><th>Branch</th><th>Status</th><th>Total</th><th>Receipt</th>
  </tr></thead><tbody>
    {sales.map(sale => <tr key={sale.id}><td><strong>#{sale.id}</strong></td><td>{dateText(sale.completed_at)}</td>
      <td>#{sale.branch_id}</td><td><span className="status-chip">{sale.status}</span></td>
      <td><strong>{money(sale.total_minor, currency)}</strong></td>
      <td><button className="table-link" onClick={() => onOpen(sale.id)}><Receipt size={15}/> View</button></td></tr>)}
    {sales.length === 0 && <tr><td colSpan={6} className="table-empty">No sales found in the loaded data.</td></tr>}
  </tbody></table></div>;
}

function LoginScreen({
  defaultBase, defaultSlug, onLoggedIn, notice, onNotice
}: {
  defaultBase: string; defaultSlug: string; onLoggedIn: () => Promise<void>;
  notice: Notice | null; onNotice: (text: string, kind: Notice['kind']) => void;
}) {
  const [apiBase, setApiBase] = useState(defaultBase);
  const [tenantSlug, setTenantSlug] = useState(defaultSlug);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [deviceName, setDeviceName] = useState('Windows Cashier');
  const [busy, setBusy] = useState(false);
  const [showConnection, setShowConnection] = useState(false);

  async function signIn(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    try {
      await login({ apiBase, tenantSlug, email, password, deviceName });
      setPassword('');
      await onLoggedIn();
    } catch (error) {
      onNotice(friendlyError(error), 'error');
    } finally { setBusy(false); }
  }

  return <div className="login-screen">
    <div className="login-art-panel"><div className="login-large-mark"><ShoppingBag size={34}/><strong>POS Cloud</strong></div>
      <div className="login-art-content"><span className="login-overline">DESKTOP CASHIER</span><h1>A smarter counter.<br/>A smoother day.</h1>
        <p>Sell, manage your register, and stay connected to your store—all in one beautiful workspace.</p>
        <div className="login-mini-card"><ShoppingCart size={24}/><div><strong>Online POS Register</strong><span>Secure • Real-time • Store connected</span></div><CheckCircle2 size={23}/></div>
      </div><small>© Web7 Softwares · POS Cloud</small>
    </div>
    <div className="login-content"><form className="login-form" onSubmit={event => void signIn(event)}>
      <div className="login-mobile-brand"><ShoppingBag/> POS Cloud</div>
      <span className="eyebrow">WELCOME BACK</span><h2>Sign in to your store</h2><p>Use your business account to access the desktop register.</p>
      {notice && <div className={'login-notice ' + notice.kind}><CircleAlert size={17}/>{notice.text}</div>}
      <label className="field">Business slug<input value={tenantSlug} onChange={event => setTenantSlug(event.target.value)}
        placeholder="your-store-slug" required autoComplete="organization"/></label>
      <label className="field">Email address<input type="email" value={email} onChange={event => setEmail(event.target.value)}
        placeholder="cashier@store.com" required autoComplete="username"/></label>
      <label className="field">Password<input type="password" value={password} onChange={event => setPassword(event.target.value)}
        placeholder="••••••••" required autoComplete="current-password"/></label>
      <button className="login-submit" disabled={busy}>{busy ? <Loader2 className="spin" size={19}/> : <ArrowRight size={19}/>}
        {busy ? 'Signing in...' : 'Sign in to POS'}</button>
      <button type="button" className="connection-toggle" onClick={() => setShowConnection(value => !value)}>
        <SlidersHorizontal size={17}/> Connection and device settings <ChevronDown size={16}/></button>
      {showConnection && <div className="login-advanced">
        <label className="field">API address<input value={apiBase} onChange={event => setApiBase(event.target.value)}
          required placeholder={DEFAULT_API}/></label>
        <label className="field">Device name<input value={deviceName} onChange={event => setDeviceName(event.target.value)}
          required maxLength={120}/></label>
        <p>HTTPS is required except for localhost development. Device tokens are stored in Windows Credential Manager.</p>
      </div>}
      <div className="login-security"><ShieldCheck size={18}/><span>Secure, device-specific access. Cashier accounts only; platform administrators use the web portal.</span></div>
    </form></div>
  </div>;
}

export default App;
