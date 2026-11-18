export type UserRole = 'admin' | 'professional' | 'client'

export interface User {
  id: number
  first_name: string
  last_name: string
  full_name: string
  email: string
  phone: string | null
  role: UserRole
  role_label: string
  is_active: boolean
  client_id?: number | null
}

export interface Paginated<T> {
  data: T[]
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  links?: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
}

export interface Client {
  id: number
  user_id: number | null
  name: string
  email: string | null
  phone: string | null
  address: string | null
  birth_date: string | null
  notes: string | null
  allergy_history: string | null
  is_active: boolean
}

export interface ServiceCategory {
  id: number
  name: string
  slug: string
  description?: string | null
  is_active: boolean
  sort_order: number
  services_count?: number
  created_at?: string
  updated_at?: string
}

export interface ProductCategory {
  id: number
  name: string
  slug: string
  description?: string | null
  is_active: boolean
  sort_order: number
  products_count?: number
  created_at?: string
  updated_at?: string
}

export interface Supplier {
  id: number
  name: string
  phone: string
  email?: string | null
  tax_id?: string | null
  address?: string | null
  delivery_time_days?: number | null
  notes?: string | null
  is_active: boolean
  products_count?: number
  created_at?: string
  updated_at?: string
}

export type PayableStatus = 'pending' | 'paid'
export type PayableDisplayStatus = 'pending' | 'paid' | 'overdue'
export type PayableType = 'expense' | 'commission' | 'supplier' | 'other'

export interface Payable {
  id: number
  description: string
  amount: string
  due_date: string
  paid_at?: string | null
  status: PayableStatus
  status_label: string
  display_status: PayableDisplayStatus
  display_status_label: string
  is_overdue: boolean
  is_paid: boolean
  type: PayableType
  type_label: string
  notes?: string | null
  supplier_id?: number | null
  professional_id?: number | null
  created_by?: number | null
  paid_by?: number | null
  supplier?: Pick<Supplier, 'id' | 'name' | 'is_active'> | null
  professional?: Pick<Professional, 'id' | 'name'> | null
  creator?: Pick<User, 'id' | 'full_name'> | null
  payer?: Pick<User, 'id' | 'full_name'> | null
  created_at?: string
  updated_at?: string
}

export interface PayablePayload {
  description: string
  amount: number | string
  due_date: string
  type: PayableType
  notes?: string | null
  supplier_id?: number | null
  professional_id?: number | null
}

export type PaymentMethod = 'cash' | 'card' | 'transfer' | 'other'
export type SaleStatus = 'paid' | 'pending' | 'cancelled'

export type ReceivableStatus = 'pending' | 'received'
export type ReceivableDisplayStatus = 'pending' | 'received' | 'overdue'
export type ReceivableReferenceFilter = 'service' | 'sale' | 'none'

export interface Receivable {
  id: number
  description: string
  amount: string
  received_at?: string | null
  status: ReceivableStatus
  status_label: string
  display_status: ReceivableDisplayStatus
  display_status_label: string
  is_overdue: boolean
  is_received: boolean
  payment_method: PaymentMethod
  payment_method_label: string
  client_id: number
  professional_id: number
  service_id?: number | null
  sale_id?: number | null
  created_by?: number | null
  received_by?: number | null
  reference_label?: string | null
  client?: Pick<Client, 'id' | 'name' | 'is_active'> | null
  professional?: Pick<Professional, 'id' | 'name'> | null
  service?: Pick<Service, 'id' | 'name'> | null
  sale?: Pick<Sale, 'id' | 'total_amount' | 'status'> | null
  creator?: Pick<User, 'id' | 'full_name'> | null
  receiver?: Pick<User, 'id' | 'full_name'> | null
  created_at?: string
  updated_at?: string
}

export interface ReceivablePayload {
  description: string
  amount: number | string
  payment_method: PaymentMethod
  client_id: number
  professional_id: number
  service_id?: number | null
  sale_id?: number | null
}

export interface CommissionPreviewRow {
  origin: 'appointment'
  origin_id: number
  date: string | null
  professional_id: number
  professional?: Pick<Professional, 'id' | 'name'> | null
  service_id: number
  service?: Pick<Service, 'id' | 'name'> | null
  base_amount: string
  percentage: string
  commission_amount: string
  source: 'professional_service' | 'professional'
  financial_status: 'calculated' | 'payable_pending' | 'paid' | string | null
  payable_id?: number | null
}

export interface CommissionPreviewResponse {
  data: CommissionPreviewRow[]
  meta: {
    mode: 'calculated_preview'
    note: string
    total_commission: string
    count: number
  }
}

export interface ProfessionalServiceSyncItem {
  service_id: number
  commission_percentage?: number | string | null
}

export interface Service {
  id: number
  name: string
  category: string | null
  service_category_id?: number | null
  service_category?: ServiceCategory | null
  description: string | null
  price: string
  duration_minutes: number
  image?: string | null
  image_url?: string | null
  is_active: boolean
  appointments_count?: number
  /** Presente quando o serviço vem do vínculo professional_service. */
  commission_percentage?: string | null
}

export interface Professional {
  id: number
  user_id?: number | null
  name: string
  specialty: string
  photo?: string | null
  image_url?: string | null
  biography?: string | null
  phone: string | null
  email: string | null
  instagram?: string | null
  facebook?: string | null
  years_experience?: number | null
  commission_percentage?: string
  is_active?: boolean
  services_count?: number
  services?: Service[]
  created_at?: string
  updated_at?: string
}

export interface ProfessionalPayload {
  name: string
  specialty: string
  email?: string | null
  phone?: string | null
  biography?: string | null
  photo?: string | null
  remove_photo?: boolean
  instagram?: string | null
  facebook?: string | null
  years_experience?: number | null
  commission_percentage?: number | string | null
  is_active: boolean
  user_id?: number | null
}

export interface PublicProfessional {
  id: number
  name: string
  specialty: string
  biography?: string | null
  photo: string | null
  image_url?: string | null
  phone: string | null
  email: string | null
  instagram?: string | null
  facebook?: string | null
  years_experience?: number | null
}

export interface AvailabilityResponse {
  date: string
  professional_id: number
  service_id: number
  slots: string[]
}

export interface ContactMessage {
  id: number
  name: string
  email: string
  phone: string | null
  subject: string | null
  message: string
  is_read: boolean
  created_at: string
}

export interface ProfessionalSchedule {
  id: number
  professional_id: number
  professional?: Pick<Professional, 'id' | 'name'> | null
  day_of_week: number
  day_name: string
  start_time: string
  end_time: string
  created_by?: number | null
  creator?: Pick<User, 'id' | 'full_name'> | null
  created_at?: string
  updated_at?: string
}

export interface ProfessionalSchedulePayload {
  professional_id: number
  day_of_week: number
  start_time: string
  end_time: string
}

export interface Appointment {
  id: number
  client_id: number
  service_id: number
  professional_id: number
  start_time: string
  end_time: string
  status: string
  notes: string | null
  total_price: string | null
  is_active: boolean
  google_event_id?: string | null
  google_sync_status?: 'pending' | 'synced' | 'failed' | 'not_applicable' | null
  google_synced_at?: string | null
  google_sync_error?: string | null
  client?: Client
  service?: Service
  professional?: Professional
}

export interface Notification {
  id: number
  title: string
  body: string
  type: string
  read_at: string | null
  is_read: boolean
  created_at?: string | null
  updated_at?: string | null
}

export interface DashboardData {
  totals?: {
    users: number
    clients: number
    professionals: number
    services: number
    products?: number
    products_low_stock?: number
    appointments_today: number
    appointments_pending: number
    sales_today?: number
    revenue_today?: string | number
  }
  low_stock_products?: Product[]
  cash_register?: CashRegisterSummary | null
  recent_appointments?: Appointment[]
  professional?: {
    appointments_today: number
    upcoming_appointments: number
  }
  upcoming_appointments?: number
  completed_appointments?: number
  next_appointment?: Appointment | null
  unread_notifications?: number
}

export interface Product {
  id: number
  name: string
  description: string | null
  sku: string | null
  barcode?: string | null
  category: string | null
  product_category_id?: number | null
  product_category?: ProductCategory | null
  supplier_id?: number | null
  supplier?: Supplier | null
  price: string
  sale_price?: string
  cost_price: string | null
  purchase_price?: string | null
  stock_quantity: number
  current_stock?: number
  min_stock: number
  minimum_stock?: number
  image: string | null
  image_url?: string | null
  is_active: boolean
  is_low_stock: boolean
}

export type StockMovementType = 'in' | 'out' | 'adjustment' | 'entry' | 'exit'

export interface StockMovement {
  id: number
  product_id: number
  user_id: number | null
  type: StockMovementType
  type_label: string
  quantity: number
  previous_quantity: number
  new_quantity: number
  reason: string | null
  notes: string | null
  product?: Product
  created_at: string
}

export interface SaleItem {
  id: number
  sale_id: number
  product_id: number | null
  service_id: number | null
  description: string
  quantity: number
  unit_price: string
  total_price: string
  product?: Product
  service?: Service
}

export interface Sale {
  id: number
  client_id: number | null
  user_id: number
  total_amount: string
  payment_method: PaymentMethod
  payment_method_label: string
  status: SaleStatus
  status_label: string
  notes: string | null
  client?: Client
  items?: SaleItem[]
  created_at: string
}

export interface LoginResponse {
  message: string
  token: string
  token_type: string
  user: User
}

export interface RegisterResponse extends LoginResponse {}

export interface CashRegisterSummary {
  id: number
  status: string
  status_label: string
  opening_amount: number
  closing_amount: number | null
  income_total: number
  expense_total: number
  expected_balance: number
  opened_at: string | null
  closed_at: string | null
  opened_by: string | null
  closed_by: string | null
  transactions_count: number
}

export interface CashReport {
  date: string
  registers: CashRegisterSummary[]
  totals: {
    opening_amount: number
    income: number
    expense: number
    expected_balance: number
    closing_amount: number
  }
}

export interface ApiMessageResponse<T = unknown> {
  message?: string
  data?: T
}

export interface SalonSettings {
  id: number | null
  salon_name: string | null
  timezone: string | null
  effective_timezone: string
  currency: string
  contact_email: string | null
  contact_phone: string | null
  address: string | null
  reminder_24h_enabled: boolean
  reminder_2h_enabled: boolean
  is_persisted: boolean
  updated_at: string | null
}

export interface PublicSalonSettings {
  salon_name: string | null
  timezone: string | null
  currency: string | null
  contact_email: string | null
  contact_phone: string | null
  address: string | null
}

export type SalonSettingsPayload = Partial<{
  salon_name: string | null
  timezone: string | null
  currency: string | null
  contact_email: string | null
  contact_phone: string | null
  address: string | null
  reminder_24h_enabled: boolean
  reminder_2h_enabled: boolean
}>

export interface GoogleCalendarStatus {
  configured: boolean
  connected: boolean
  is_active: boolean
  ready: boolean
  google_account_email: string | null
  calendar_id: string | null
  calendar_name: string | null
  last_synced_at: string | null
  connected_by: number | null
}

export interface GoogleCalendarListItem {
  id: string
  name: string
  primary: boolean
}

