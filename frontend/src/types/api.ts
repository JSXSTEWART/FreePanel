// API Response types
export interface ApiResponse<T = unknown> {
  success: boolean
  message?: string
  data?: T
  errors?: Record<string, string[]>
}

export interface PaginatedResponse<T> {
  success: boolean
  data: T[]
  meta: {
    current_page: number
    from: number
    last_page: number
    per_page: number
    to: number
    total: number
  }
}

// Domain types
export interface Domain {
  id: number
  account_id: number
  name: string
  type: 'main' | 'addon' | 'alias' | 'parked'
  document_root: string
  is_active: boolean
  ssl_enabled: boolean
  php_version: string
  created_at: string
  updated_at: string
}

export interface Subdomain {
  id: number
  domain_id: number
  name: string
  document_root: string
  is_active: boolean
  created_at: string
}

// DNS types
export interface DnsZone {
  id: number
  domain_id: number
  serial: number
  ttl: number
  primary_ns: string
  admin_email: string
}

export interface DnsRecord {
  id: number
  zone_id: number
  name: string
  type: 'A' | 'AAAA' | 'CNAME' | 'MX' | 'TXT' | 'NS' | 'SRV' | 'CAA' | 'PTR'
  content: string
  ttl: number
  priority?: number
  is_system: boolean
}

// Email types
export interface EmailAccount {
  id: number
  domain_id: number
  local_part: string
  email: string
  quota: number
  quota_used: number
  is_active: boolean
  created_at: string
}

export interface EmailForwarder {
  id: number
  domain_id: number
  source: string
  destination: string
  is_active: boolean
}

// Database types
export interface Database {
  id: number
  account_id: number
  name: string
  type: 'mysql' | 'postgresql'
  size: number
  size_human: string
  created_at: string
}

export interface DatabaseUser {
  id: number
  account_id: number
  username: string
  host: string
  type: 'mysql' | 'postgresql'
}

// SSL types
export interface SslCertificate {
  id: number
  domain_id: number
  type: 'self_signed' | 'lets_encrypt' | 'custom'
  issued_at: string
  expires_at: string
  days_until_expiry: number
  is_active: boolean
  auto_renew: boolean
}

// Backup types
export interface Backup {
  id: number
  account_id: number
  type: 'full' | 'files' | 'databases' | 'emails'
  filename: string
  size: number
  size_human: string
  status: 'pending' | 'running' | 'completed' | 'failed'
  error_message?: string
  completed_at?: string
  created_at: string
}

// Package types
export interface Package {
  id: number
  name: string
  description?: string
  disk_quota: number
  disk_quota_human: string
  bandwidth_quota: number
  bandwidth_quota_human: string
  max_domains: number
  max_subdomains: number
  max_email_accounts: number
  max_databases: number
  max_ftp_accounts: number
  is_active: boolean
}

// Account types
export interface Account {
  id: number
  uuid: string
  user_id: number
  package_id: number
  username: string
  domain: string
  status: 'active' | 'suspended' | 'terminated'
  disk_used: number
  bandwidth_used: number
  suspend_reason?: string
  suspended_at?: string
  created_at: string
  package?: Package
  user?: {
    id: number
    username: string
    email: string
  }
}

// Service types
export interface ServiceStatus {
  name: string
  systemd_name: string
  is_active: boolean
  is_enabled: boolean
  uptime?: string
}

// Server info types
export interface ServerInfo {
  hostname: string
  os: string
  kernel: string
  uptime: string
  load: number[]
  memory: {
    total: number
    used: number
    free: number
    percent: number
  }
  disk: {
    total: number
    used: number
    free: number
    percent: number
  }
}
