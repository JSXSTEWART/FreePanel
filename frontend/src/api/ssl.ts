import client from "./client";

export interface SslCertificate {
  id: number;
  domain: string;
  type: "lets_encrypt" | "custom" | "self_signed";
  issuer: string;
  subject: string;
  valid_from: string;
  valid_to: string;
  is_valid: boolean;
  is_expiring_soon: boolean;
  created_at: string;
}

export interface CsrRequest {
  domain: string;
  country: string;
  state: string;
  locality: string;
  organization: string;
  organizational_unit?: string;
  email?: string;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export const sslApi = {
  list: async (): Promise<SslCertificate[]> => {
    const response =
      await client.get<ApiResponse<SslCertificate[]>>("/ssl/certificates");
    return response.data.data;
  },

  get: async (id: number): Promise<SslCertificate> => {
    const response = await client.get<ApiResponse<SslCertificate>>(
      `/ssl/certificates/${id}`,
    );
    return response.data.data;
  },

  install: async (
    domain: string,
    data: {
      certificate: string;
      private_key: string;
      ca_bundle?: string;
    },
  ): Promise<SslCertificate> => {
    const response = await client.post<ApiResponse<SslCertificate>>(
      "/ssl/certificates",
      {
        domain,
        ...data,
      },
    );
    return response.data.data;
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/ssl/certificates/${id}`);
  },

  generateCsr: async (
    data: CsrRequest,
  ): Promise<{
    csr: string;
    private_key: string;
  }> => {
    const response = await client.post("/ssl/generate-csr", data);
    return response.data.data;
  },

  requestLetsEncrypt: async (domain: string): Promise<SslCertificate> => {
    const response = await client.post<ApiResponse<SslCertificate>>(
      "/ssl/lets-encrypt",
      { domain },
    );
    return response.data.data;
  },

  getAutoSslStatus: async (): Promise<{
    enabled: boolean;
    last_run?: string;
    pending_domains: string[];
  }> => {
    const response = await client.get("/ssl/auto-ssl/status");
    return response.data.data;
  },
};

export default sslApi;
