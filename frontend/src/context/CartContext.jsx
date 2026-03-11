import React, { createContext, useContext, useState, useRef, useCallback } from 'react';

const CartContext = createContext(null);

export const useCart = () => useContext(CartContext);

export const CartProvider = ({ children }) => {
  const [items, setItems] = useState([]);
  const [appliedCoupon, setAppliedCoupon] = useState(null); // { code, discount_type, discount_value, discount, vendor_id, description }
  const onCartOpenRef = useRef(null);

  const setOnCartOpen = useCallback((fn) => {
    onCartOpenRef.current = fn;
  }, []);

  const addItem = (product) => {
    const maxStock = Number(product.stock);
    setItems((prev) => {
      const existing = prev.find((i) => i.id === product.id);
      if (existing) {
        if (maxStock && existing.quantity >= maxStock) return prev;
        return prev.map((i) =>
          i.id === product.id ? { ...i, quantity: i.quantity + 1 } : i
        );
      }
      if (maxStock === 0) return prev;
      return [...prev, { ...product, quantity: 1 }];
    });
    // Abrir carrito flotante automáticamente
    if (onCartOpenRef.current) onCartOpenRef.current();
  };

  const removeItem = (productId) => {
    setItems((prev) => prev.filter((i) => i.id !== productId));
  };

  const updateQuantity = (productId, quantity) => {
    if (quantity <= 0) {
      removeItem(productId);
      return;
    }
    setItems((prev) =>
      prev.map((i) => {
        if (i.id !== productId) return i;
        const maxStock = Number(i.stock);
        const safeQty = maxStock ? Math.min(quantity, maxStock) : quantity;
        return { ...i, quantity: safeQty };
      })
    );
  };

  const clearCart = () => { setItems([]); setAppliedCoupon(null); };

  const totalItems = items.reduce((sum, i) => sum + i.quantity, 0);
  const subtotal = items.reduce((sum, i) => sum + parseFloat(i.price) * i.quantity, 0);
  const discountAmount = appliedCoupon ? appliedCoupon.discount : 0;

  const getItemQuantity = (productId) => {
    const item = items.find((i) => i.id === productId);
    return item ? item.quantity : 0;
  };

  return (
    <CartContext.Provider value={{
      items, addItem, removeItem, updateQuantity, clearCart,
      totalItems, subtotal, setOnCartOpen, getItemQuantity,
      appliedCoupon, setAppliedCoupon, discountAmount,
    }}>
      {children}
    </CartContext.Provider>
  );
};
