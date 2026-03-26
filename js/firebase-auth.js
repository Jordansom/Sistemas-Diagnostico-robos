// firebase-auth.js — Autenticación con Firebase Auth (Email/Password)
import { auth } from "./firebase-config.js";
import {
  signInWithEmailAndPassword,
  createUserWithEmailAndPassword,
  updateProfile,
  signOut,
  onAuthStateChanged
} from "https://www.gstatic.com/firebasejs/12.11.0/firebase-auth.js";

// Código de registro requerido (mismo que el PHP original)
const REGISTRATION_CODE = "qualitas2025";

/**
 * Inicia sesión con email y contraseña.
 * El username se convierte a email ficticio: username@iso-39.firebaseapp.com
 */
export async function loginUser(username, password) {
  const email = usernameToEmail(username);
  const userCredential = await signInWithEmailAndPassword(auth, email, password);
  return userCredential.user;
}

/**
 * Registra un nuevo usuario.
 * Valida el código de registro antes de crear la cuenta.
 */
export async function registerUser(username, password, code) {
  if (!username || !password || !code) {
    throw new Error("Todos los campos son obligatorios.");
  }
  if (code !== REGISTRATION_CODE) {
    throw new Error("El código de registro es incorrecto.");
  }
  const email = usernameToEmail(username);
  const userCredential = await createUserWithEmailAndPassword(auth, email, password);
  // Guardar el nombre de usuario original en el perfil
  await updateProfile(userCredential.user, { displayName: username });
  return userCredential.user;
}

/**
 * Cierra la sesión actual.
 */
export async function logoutUser() {
  await signOut(auth);
}

/**
 * Escucha cambios en el estado de autenticación.
 */
export function onAuthChange(callback) {
  return onAuthStateChanged(auth, callback);
}

/**
 * Obtiene el usuario actual.
 */
export function getCurrentUser() {
  return auth.currentUser;
}

/**
 * Convierte un nombre de usuario a un email ficticio para Firebase Auth.
 * Firebase Auth requiere formato email, así que normalizamos el username.
 */
function usernameToEmail(username) {
  // Normalizar: quitar espacios, acentos, convertir a minúsculas
  const normalized = username
    .toLowerCase()
    .trim()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")  // quitar acentos
    .replace(/[^a-z0-9._-]/g, "_");   // caracteres no válidos -> _
  return `${normalized}@iso-39.firebaseapp.com`;
}
