import client from "./client";

export interface EmailAccount {
  id: number;
  email: string;
  domain: string;
  quota: number;
  quota_used: number;
  created_at: string;
}

export interface EmailForwarder {
  id: number;
  source: string;
  destination: string;
  created_at: string;
}

export interface EmailAutoresponder {
  id: number;
  email: string;
  subject: string;
  body: string;
  start_time?: string;
  end_time?: string;
  is_active: boolean;
  created_at: string;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export const emailApi = {
  // Email Accounts
  listAccounts: async (): Promise<EmailAccount[]> => {
    const response =
      await client.get<ApiResponse<EmailAccount[]>>("/email/accounts");
    return response.data.data;
  },

  createAccount: async (data: {
    email: string;
    password: string;
    quota?: number;
  }): Promise<EmailAccount> => {
    const response = await client.post<ApiResponse<EmailAccount>>(
      "/email/accounts",
      data,
    );
    return response.data.data;
  },

  getAccount: async (id: number): Promise<EmailAccount> => {
    const response = await client.get<ApiResponse<EmailAccount>>(
      `/email/accounts/${id}`,
    );
    return response.data.data;
  },

  updateAccount: async (
    id: number,
    data: {
      quota?: number;
    },
  ): Promise<EmailAccount> => {
    const response = await client.put<ApiResponse<EmailAccount>>(
      `/email/accounts/${id}`,
      data,
    );
    return response.data.data;
  },

  deleteAccount: async (id: number): Promise<void> => {
    await client.delete(`/email/accounts/${id}`);
  },

  changePassword: async (id: number, password: string): Promise<void> => {
    await client.put(`/email/accounts/${id}/password`, { password });
  },

  updateQuota: async (id: number, quota: number): Promise<void> => {
    await client.put(`/email/accounts/${id}/quota`, { quota });
  },

  getUsage: async (id: number): Promise<{ used: number; limit: number }> => {
    const response = await client.get(`/email/accounts/${id}/usage`);
    return response.data.data;
  },

  // Forwarders
  listForwarders: async (): Promise<EmailForwarder[]> => {
    const response =
      await client.get<ApiResponse<EmailForwarder[]>>("/email/forwarders");
    return response.data.data;
  },

  createForwarder: async (data: {
    source: string;
    destination: string;
  }): Promise<EmailForwarder> => {
    const response = await client.post<ApiResponse<EmailForwarder>>(
      "/email/forwarders",
      data,
    );
    return response.data.data;
  },

  deleteForwarder: async (id: number): Promise<void> => {
    await client.delete(`/email/forwarders/${id}`);
  },

  // Autoresponders
  listAutoresponders: async (): Promise<EmailAutoresponder[]> => {
    const response = await client.get<ApiResponse<EmailAutoresponder[]>>(
      "/email/autoresponders",
    );
    return response.data.data;
  },

  createAutoresponder: async (data: {
    email: string;
    subject: string;
    body: string;
    start_time?: string;
    end_time?: string;
  }): Promise<EmailAutoresponder> => {
    const response = await client.post<ApiResponse<EmailAutoresponder>>(
      "/email/autoresponders",
      data,
    );
    return response.data.data;
  },

  updateAutoresponder: async (
    id: number,
    data: Partial<{
      subject: string;
      body: string;
      start_time?: string;
      end_time?: string;
      is_active: boolean;
    }>,
  ): Promise<EmailAutoresponder> => {
    const response = await client.put<ApiResponse<EmailAutoresponder>>(
      `/email/autoresponders/${id}`,
      data,
    );
    return response.data.data;
  },

  deleteAutoresponder: async (id: number): Promise<void> => {
    await client.delete(`/email/autoresponders/${id}`);
  },
};

export default emailApi;
