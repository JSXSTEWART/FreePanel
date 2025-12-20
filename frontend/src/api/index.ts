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

// New API modules
export { default as cronApi } from './cron'
export type { CronJob, CronPreset } from './cron'

export { default as securityApi } from './security'
export type { BlockedIp, HotlinkProtection, ProtectedDirectory, ProtectedDirectoryUser } from './security'

export { default as gitApi } from './git'
export type { GitRepository, DeployLog, GitFile } from './git'

export { default as terminalApi } from './terminal'
export type { TerminalSession, CommandResult, CommandHistory, AutocompleteResult } from './terminal'

export { default as errorPagesApi } from './errorPages'
export type { ErrorPage, ErrorCode } from './errorPages'

export { default as redirectsApi } from './redirects'
export type { Redirect } from './redirects'

export { default as phpApi } from './php'
export type { PhpConfig, PhpVersion, PhpExtension, PhpInfo } from './php'

export { default as emailFiltersApi } from './emailFilters'
export type { EmailFilter, FilterOptions, SpamSettings } from './emailFilters'

export { default as applicationsApi } from './applications'
export type { Application, Runtime, ApplicationLogs, ApplicationMetrics } from './applications'

export { default as mimeTypesApi } from './mimeTypes'
export type { MimeType, ApacheHandler, DirectoryIndexes } from './mimeTypes'

export { default as remoteMysqlApi } from './remoteMysql'
export type { RemoteMysqlHost, RemoteMysqlTestResult } from './remoteMysql'

export { default as diskUsageApi } from './diskUsage'
export type { DiskUsageSummary, DirectoryUsage, DatabaseUsage, EmailUsage, LargestFile, FilesByType } from './diskUsage'

export { default as accessLogsApi } from './accessLogs'
export type { AccessLogEntry, AccessLogStats, LogFile } from './accessLogs'

export { default as dnsApi } from './dns'
export type { DnsZone, DnsRecord } from './dns'

export { default as zapierApi } from './zapier'
export type { ZapierConnection, ZapierTool, ZapierExecuteResult } from './zapier'

export { default as planApi } from './plan'
export type { Plan, AccountUsage, Feature, FeatureGate } from './plan'
