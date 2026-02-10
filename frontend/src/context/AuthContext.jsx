import React, { createContext, useContext, useState, useEffect } from 'react';
import { login as apiLogin, register as apiRegister } from '../services/api';

const AuthContext = createContext(null);

export const useAuth = () => useContext(AuthContext);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  // Cargar usuario desde localStorage al iniciar
  useEffect(() => {
    const stored = localStorage.getItem('petsgo_user');
    if (stored) {
      try {
        setUser(JSON.parse(stored));
      } catch {
        localStorage.removeItem('petsgo_user');
      }
    }
    setLoading(false);
  }, []);

  const login = async (username, password) => {
    const { data } = await apiLogin(username, password);
    const userData = {
      id: data.user_id,
      username: data.username,
      email: data.email,
      displayName: data.display_name,
      role: data.role,
    };
    localStorage.setItem('petsgo_token', data.token);
    localStorage.setItem('petsgo_user', JSON.stringify(userData));
    setUser(userData);
    return userData;
  };

  const register = async (username, email, password) => {
    const { data } = await apiRegister(username, email, password);
    return data;
  };

  const logout = () => {
    localStorage.removeItem('petsgo_token');
    localStorage.removeItem('petsgo_nonce');
    localStorage.removeItem('petsgo_user');
    setUser(null);
  };

  const isAdmin = () => user?.role === 'admin';
  const isVendor = () => user?.role === 'vendor';
  const isRider = () => user?.role === 'rider';

  return (
    <AuthContext.Provider value={{
      user, loading, login, register, logout,
      isAdmin, isVendor, isRider, isAuthenticated: !!user,
    }}>
      {children}
    </AuthContext.Provider>
  );
};
