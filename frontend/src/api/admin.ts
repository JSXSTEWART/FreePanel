import client from "./client";

// Types
export interface Account {
  id: number;
  uuid: string;
  username: string;
  domain: string;
  email: string;
  status: "active" | "suspended" | "terminated";
  disk_used: number;
  bandwidth_used: number;
  created_at: string;
  suspended_at?: string;
  suspension_reason?: string;
  user: {
    id: number;
    email: string;
    is_active: boolean;
  };
  package: {
    id: number;
    name: string;
    disk_quota: number;
    bandwidth: number;
  };
}

export interface AccountCreateRequest {
  username: string;
  password: string;
  email: string;
  domain: string;
  package_id: number;
  reseller_id?: number;
}

export interface Package {
  id: number;
  name: string;
  disk_quota: number;
  bandwidth: number;
  max_addon_domains: number;
  max_subdomains: number;
  max_email_accounts: number;
  max_databases: number;
  max_ftp_accounts: number;
  max_parked_domains: number;
  is_default: boolean;
  features: Record<string, boolean>;
  accounts_count?: number;
  created_at: string;
}

export interface PackageCreateRequest {
  name: string;
  disk_quota: number;
  bandwidth: number;
  max_addon_domains: number;
  max_subdomains: number;
  max_email_accounts: number;
  max_databases: number;
  max_ftp_accounts: number;
  max_parked_domains?: number;
  is_default?: boolean;
  features?: Record<string, boolean>;
}

export interface Service {
  id: string;
  service_name: string;
  display_name: string;
  status: string;
  is_running: boolean;
  is_enabled: boolean;
  uptime?: string;
  pid?: number;
  memory?: string;
  cpu?: string;
}

export interface Reseller {
  id: number;
  user_id: number;
  max_accounts: number;
  disk_limit: number;
  bandwidth_limit: number;
  nameservers: string[];
  branding: Record<string, string>;
  allowed_packages: number[];
  account_count?: number;
  total_disk_used?: number;
  total_bandwidth_used?: number;
  user: {
    id: number;
    username: string;
    email: string;
    is_active: boolean;
  };
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

// Accounts API
export const accountsApi = {
  list: async (params?: {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string;
    package_id?: number;
  }): Promise<PaginatedResponse<Account>> => {
    const response = await client.get<ApiResponse<PaginatedResponse<Account>>>(
      "/admin/accounts",
      { params },
    );
    return response.data.data;
  },

  get: async (id: number): Promise<Account> => {
    const response = await client.get<ApiResponse<Account>>(
      `/admin/accounts/${id}`,
    );
    return response.data.data;
  },

  create: async (data: AccountCreateRequest): Promise<Account> => {
    const response = await client.post<ApiResponse<Account>>(
      "/admin/accounts",
      data,
    );
    return response.data.data;
  },

  update: async (
    id: number,
    data: Partial<AccountCreateRequest>,
  ): Promise<Account> => {
    const response = await client.put<ApiResponse<Account>>(
      `/admin/accounts/${id}`,
      data,
    );
    return response.data.data;
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/admin/accounts/${id}`);
  },

  suspend: async (id: number, reason?: string): Promise<Account> => {
    const response = await client.post<ApiResponse<Account>>(
      `/admin/accounts/${id}/suspend`,
      { reason },
    );
    return response.data.data;
  },

  unsuspend: async (id: number): Promise<Account> => {
    const response = await client.post<ApiResponse<Account>>(
      `/admin/accounts/${id}/unsuspend`,
    );
    return response.data.data;
  },

  changePackage: async (id: number, packageId: number): Promise<Account> => {
    const response = await client.post<ApiResponse<Account>>(
      `/admin/accounts/${id}/change-package`,
      {
        package_id: packageId,
      },
    );
    return response.data.data;
  },

  getUsage: async (
    id: number,
  ): Promise<{
    disk: { used: number; limit: number; percent: number };
    bandwidth: { used: number; limit: number; percent: number };
    quotas: Record<string, { used: number; limit: number }>;
  }> => {
    const response = await client.get(`/admin/accounts/${id}/usage`);
    return response.data.data;
  },
};

// Packages API
export const packagesApi = {
  list: async (params?: { search?: string }): Promise<Package[]> => {
    const response = await client.get<ApiResponse<Package[]>>(
      "/admin/packages",
      { params },
    );
    return response.data.data;
  },

  get: async (id: number): Promise<Package> => {
    const response = await client.get<ApiResponse<Package>>(
      `/admin/packages/${id}`,
    );
    return response.data.data;
  },

  create: async (data: PackageCreateRequest): Promise<Package> => {
    const response = await client.post<ApiResponse<Package>>(
      "/admin/packages",
      data,
    );
    return response.data.data;
  },

  update: async (
    id: number,
    data: Partial<PackageCreateRequest>,
  ): Promise<Package> => {
    const response = await client.put<ApiResponse<Package>>(
      `/admin/packages/${id}`,
      data,
    );
    return response.data.data;
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/admin/packages/${id}`);
  },
};

// Services API
export const servicesApi = {
  list: async (): Promise<Service[]> => {
    const response =
      await client.get<ApiResponse<Service[]>>("/admin/services");
    return response.data.data;
  },

  getStatus: async (serviceId: string): Promise<Service> => {
    const response = await client.get<ApiResponse<Service>>(
      `/admin/services/${serviceId}/status`,
    );
    return response.data.data;
  },

  start: async (serviceId: string): Promise<void> => {
    await client.post(`/admin/services/${serviceId}/start`);
  },

  stop: async (serviceId: string): Promise<void> => {
    await client.post(`/admin/services/${serviceId}/stop`);
  },

  restart: async (serviceId: string): Promise<void> => {
    await client.post(`/admin/services/${serviceId}/restart`);
  },
};

// Resellers API
export const resellersApi = {
  list: async (params?: {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string;
  }): Promise<PaginatedResponse<Reseller>> => {
    const response = await client.get<ApiResponse<PaginatedResponse<Reseller>>>(
      "/admin/resellers",
      { params },
    );
    return response.data.data;
  },

  get: async (id: number): Promise<Reseller> => {
    const response = await client.get<ApiResponse<Reseller>>(
      `/admin/resellers/${id}`,
    );
    return response.data.data;
  },

  create: async (data: {
    username: string;
    email: string;
    password: string;
    max_accounts: number;
    disk_limit: number;
    bandwidth_limit: number;
    nameservers?: string[];
    allowed_packages?: number[];
  }): Promise<Reseller> => {
    const response = await client.post<ApiResponse<Reseller>>(
      "/admin/resellers",
      data,
    );
    return response.data.data;
  },

  update: async (
    id: number,
    data: Partial<{
      email: string;
      max_accounts: number;
      disk_limit: number;
      bandwidth_limit: number;
      nameservers: string[];
      allowed_packages: number[];
      is_active: boolean;
    }>,
  ): Promise<Reseller> => {
    const response = await client.put<ApiResponse<Reseller>>(
      `/admin/resellers/${id}`,
      data,
    );
    return response.data.data;
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/admin/resellers/${id}`);
  },

  getAccounts: async (
    id: number,
    params?: {
      page?: number;
      search?: string;
      status?: string;
    },
  ): Promise<PaginatedResponse<Account>> => {
    const response = await client.get<ApiResponse<PaginatedResponse<Account>>>(
      `/admin/resellers/${id}/accounts`,
      { params },
    );
    return response.data.data;
  },
};

// Server API
export const serverApi = {
  getInfo: async (): Promise<{
    hostname: string;
    os: string;
    kernel: string;
    uptime: string;
    load: number[];
  }> => {
    const response = await client.get("/admin/server/info");
    return response.data.data;
  },

  getLoad: async (): Promise<{
    cpu: number;
    memory: { used: number; total: number; percent: number };
    load: number[];
  }> => {
    const response = await client.get("/admin/server/load");
    return response.data.data;
  },

  getDisk: async (): Promise<
    Array<{
      filesystem: string;
      mount: string;
      size: number;
      used: number;
      available: number;
      percent: number;
    }>
  > => {
    const response = await client.get("/admin/server/disk");
    return response.data.data;
  },

  getProcesses: async (): Promise<
    Array<{
      pid: number;
      user: string;
      cpu: number;
      memory: number;
      command: string;
    }>
  > => {
    const response = await client.get("/admin/server/processes");
    return response.data.data;
  },

  getIPs: async (): Promise<
    Array<{
      ip: string;
      interface: string;
      type: "ipv4" | "ipv6";
    }>
  > => {
    const response = await client.get("/admin/server/ips");
    return response.data.data;
  },
};
