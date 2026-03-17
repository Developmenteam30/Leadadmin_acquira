import { ref } from 'vue';
import axios from 'axios';
import router from '../router';

const token = ref(localStorage.getItem('auth_token') || null);
const user = ref(JSON.parse(localStorage.getItem('auth_user') || 'null'));

// Configure axios defaults
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['Content-Type'] = 'application/json';

// Add token to requests if available
if (token.value) {
  axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
}

// Interceptor to add token to requests
axios.interceptors.request.use(
  (config) => {
    const authToken = localStorage.getItem('auth_token');
    if (authToken) {
      config.headers.Authorization = `Bearer ${authToken}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Interceptor to handle 401 errors
axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      logout();
      router.push('/login');
    }
    return Promise.reject(error);
  }
);

export function useAuth() {
  const login = async (username, password) => {
    try {
      const response = await axios.post('/api/login', {
        username,
        password,
      });

      if (response.data.status === 1) {
        token.value = response.data.token;
        user.value = response.data.user;
        
        localStorage.setItem('auth_token', response.data.token);
        localStorage.setItem('auth_user', JSON.stringify(response.data.user));
        
        axios.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`;
        
        return { success: true };
      } else {
        return { success: false, error: response.data.error || 'Login failed' };
      }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || error.message || 'Login failed',
      };
    }
  };

  const logout = async () => {
    try {
      if (token.value) {
        await axios.post('/api/logout');
      }
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      token.value = null;
      user.value = null;
      localStorage.removeItem('auth_token');
      localStorage.removeItem('auth_user');
      delete axios.defaults.headers.common['Authorization'];
    }
  };

  const checkAuth = async () => {
    if (!token.value) {
      return false;
    }

    try {
      const response = await axios.get('/api/me');
      user.value = response.data.user;
      localStorage.setItem('auth_user', JSON.stringify(response.data.user));
      return true;
    } catch (error) {
      logout();
      return false;
    }
  };

  const getUser = () => {
    return user.value;
  };

  const isAuthenticated = () => {
    return !!token.value;
  };

  return {
    token,
    user,
    login,
    logout,
    checkAuth,
    getUser,
    isAuthenticated,
  };
}
