import client from "./client";

export interface FtpAccount {
  id: number;
  username: string;
  directory: string;
  quota?: number;
  quota_used?: number;
  created_at: string;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export const ftpApi = {
  list: async (): Promise<FtpAccount[]> => {
    const response =
      await client.get<ApiResponse<FtpAccount[]>>("/ftp/accounts");
    return response.data.data;
  },

  create: async (data: {
    username: string;
    password: string;
    directory: string;
    quota?: number;
  }): Promise<FtpAccount> => {
    const response = await client.post<ApiResponse<FtpAccount>>(
      "/ftp/accounts",
      data,
    );
    return response.data.data;
  },

  get: async (id: number): Promise<FtpAccount> => {
    const response = await client.get<ApiResponse<FtpAccount>>(
      `/ftp/accounts/${id}`,
    );
    return response.data.data;
  },

  update: async (
    id: number,
    data: {
      directory?: string;
      quota?: number;
    },
  ): Promise<FtpAccount> => {
    const response = await client.put<ApiResponse<FtpAccount>>(
      `/ftp/accounts/${id}`,
      data,
    );
    return response.data.data;
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/ftp/accounts/${id}`);
  },

  changePassword: async (id: number, password: string): Promise<void> => {
    await client.put(`/ftp/accounts/${id}/password`, { password });
  },
};

export default ftpApi;
