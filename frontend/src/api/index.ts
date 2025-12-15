// API Client
export { default as client, setAuthToken, getAuthToken } from './client'

// Auth API
export * from './auth'

// Admin APIs
export {
  accountsApi,
  packagesApi,
  servicesApi,
  resellersApi,
  serverApi,
} from './admin'
export type {
  Account,
  AccountCreateRequest,
  Package,
  PackageCreateRequest,
  Service,
  Reseller,
  PaginatedResponse,
  ApiResponse,
} from './admin'

// User APIs
export { default as domainsApi } from './domains'
export type { Domain, Subdomain } from './domains'

export { default as emailApi } from './email'
export type { EmailAccount, EmailForwarder, EmailAutoresponder } from './email'

export { default as databasesApi } from './databases'
export type { Database, DatabaseUser } from './databases'

export { default as filesApi } from './files'
export type { FileItem, QuotaInfo } from './files'

export { default as sslApi } from './ssl'
export type { SslCertificate, CsrRequest } from './ssl'

export { default as backupsApi } from './backups'
export type { Backup } from './backups'

export { default as statsApi } from './stats'
export type { BandwidthStats, VisitorStats, ErrorStats, ResourceUsage } from './stats'

export { default as appsApi } from './apps'
export type { AvailableApp, InstalledApp } from './apps'

export { default as ftpApi } from './ftp'
export type { FtpAccount } from './ftp'
