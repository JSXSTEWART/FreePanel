import client from "./client";

export interface Domain {
  id: number;
  account_id: number;
  name: string;
  document_root: string;
  is_main: boolean;
  status: "active" | "pending" | "suspended";
  php_version?: string;
  created_at: string;
  subdomains?: Subdomain[];
  ssl_certificate?: {
    id: number;
    issuer: string;
    expires_at: string;
  };
}

export interface Subdomain {
  id: number;
  domain_id: number;
  name: string;
  document_root: string;
  created_at: string;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export const domainsApi = {
  list: async (): Promise<Domain[]> => {
    const response = await client.get<ApiResponse<Domain[]>>("/domains");
    return response.data.data;
  },

  get: async (id: number): Promise<Domain> => {
    const response = await client.get<ApiResponse<Domain>>(`/domains/${id}`);
    return response.data.data;
  },

  create: async (data: {
    name: string;
    document_root?: string;
  }): Promise<Domain> => {
    const response = await client.post<ApiResponse<Domain>>("/domains", data);
    return response.data.data;
  },

  update: async (
    id: number,
    data: {
      document_root?: string;
      php_version?: string;
    },
  ): Promise<Domain> => {
    const response = await client.put<ApiResponse<Domain>>(
      `/domains/${id}`,
      data,
    );
    return response.data.data;
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/domains/${id}`);
  },

  // Subdomains
  getSubdomains: async (domainId: number): Promise<Subdomain[]> => {
    const response = await client.get<ApiResponse<Subdomain[]>>(
      `/domains/${domainId}/subdomains`,
    );
    return response.data.data;
  },

  createSubdomain: async (
    domainId: number,
    data: {
      name: string;
      document_root?: string;
    },
  ): Promise<Subdomain> => {
    const response = await client.post<ApiResponse<Subdomain>>(
      `/domains/${domainId}/subdomains`,
      data,
    );
    return response.data.data;
  },

  deleteSubdomain: async (
    domainId: number,
    subdomainId: number,
  ): Promise<void> => {
    await client.delete(`/domains/${domainId}/subdomains/${subdomainId}`);
  },

  // Redirects
  addRedirect: async (
    domainId: number,
    data: {
      source: string;
      destination: string;
      type: "permanent" | "temporary";
    },
  ): Promise<void> => {
    await client.post(`/domains/${domainId}/redirects`, data);
  },

  removeRedirect: async (
    domainId: number,
    redirectId: number,
  ): Promise<void> => {
    await client.delete(`/domains/${domainId}/redirects/${redirectId}`);
  },
};

export default domainsApi;
