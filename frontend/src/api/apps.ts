import client from "./client";

export interface AvailableApp {
  id: string;
  name: string;
  version: string;
  description: string;
  icon: string;
  category: string;
  requirements?: {
    php_version?: string;
    database?: boolean;
  };
}

export interface InstalledApp {
  id: number;
  app_id: string;
  name: string;
  version: string;
  domain: string;
  path: string;
  database_name?: string;
  admin_url?: string;
  installed_at: string;
  updated_at?: string;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export const appsApi = {
  getAvailable: async (): Promise<AvailableApp[]> => {
    const response =
      await client.get<ApiResponse<AvailableApp[]>>("/apps/available");
    return response.data.data;
  },

  getInstalled: async (): Promise<InstalledApp[]> => {
    const response =
      await client.get<ApiResponse<InstalledApp[]>>("/apps/installed");
    return response.data.data;
  },

  install: async (data: {
    app_id: string;
    domain: string;
    path: string;
    admin_user?: string;
    admin_password?: string;
    admin_email?: string;
    site_name?: string;
  }): Promise<InstalledApp> => {
    const response = await client.post<ApiResponse<InstalledApp>>(
      "/apps/install",
      data,
    );
    return response.data.data;
  },

  uninstall: async (id: number): Promise<void> => {
    await client.delete(`/apps/${id}`);
  },

  update: async (id: number): Promise<InstalledApp> => {
    const response = await client.post<ApiResponse<InstalledApp>>(
      `/apps/${id}/update`,
    );
    return response.data.data;
  },

  createStaging: async (
    id: number,
    data: {
      subdomain: string;
    },
  ): Promise<InstalledApp> => {
    const response = await client.post<ApiResponse<InstalledApp>>(
      `/apps/${id}/staging`,
      data,
    );
    return response.data.data;
  },
};

export default appsApi;
