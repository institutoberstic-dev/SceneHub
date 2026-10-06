import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import AppErrorBoundary from './components/AppErrorBoundary';
import AppLayout from './components/AppLayout';
import DashboardPage from './pages/DashboardPage';
import EmotionsPage from './pages/EmotionsPage';
import EmotionDetailPage from './pages/EmotionDetailPage';
import ForgotPasswordPage from './pages/ForgotPasswordPage';
import LoginPage from './pages/LoginPage';
import ProfilePage from './pages/ProfilePage';
import ResetPasswordPage from './pages/ResetPasswordPage';
import ResultsPage from './pages/ResultsPage';
import RolesPage from './pages/RolesPage';
import ScenariosPage from './pages/ScenariosPage';
import ScenarioDetailPage from './pages/ScenarioDetailPage';
import UsersPage from './pages/UsersPage';

function ShellRoute({ children }) {
    return <AppLayout>{children}</AppLayout>;
}

function PermissionRoute({ permission, children }) {
    let user = null;
    try { user = JSON.parse(sessionStorage.getItem('scenehub_user') || 'null'); } catch { /* La ruta del servidor volverá a validar la sesión. */ }
    const allowed = user?.roles?.includes('admin') || user?.permissions?.includes(permission);

    return allowed ? children : <Navigate to="/dashboard" replace />;
}

function App() {
    return (
        <BrowserRouter>
            <Routes>
                <Route path="/login" element={<LoginPage />} />
                <Route path="/recuperar-contrasena" element={<ForgotPasswordPage />} />
                <Route path="/restablecer-contrasena/:token" element={<ResetPasswordPage />} />
                <Route path="/dashboard" element={<ShellRoute><DashboardPage /></ShellRoute>} />
                <Route path="/perfil" element={<ShellRoute><ProfilePage /></ShellRoute>} />
                <Route path="/escenarios" element={<PermissionRoute permission="escenarios.leer"><ShellRoute><ScenariosPage /></ShellRoute></PermissionRoute>} />
                <Route path="/escenarios/:id" element={<PermissionRoute permission="escenarios.leer"><ShellRoute><ScenarioDetailPage /></ShellRoute></PermissionRoute>} />
                <Route path="/resultados" element={<PermissionRoute permission="escenarios.leer"><ShellRoute><ResultsPage /></ShellRoute></PermissionRoute>} />
                <Route path="/emociones" element={<PermissionRoute permission="emociones.leer"><ShellRoute><EmotionsPage /></ShellRoute></PermissionRoute>} />
                <Route path="/emociones/:id" element={<PermissionRoute permission="emociones.leer"><ShellRoute><EmotionDetailPage /></ShellRoute></PermissionRoute>} />
                <Route path="/users-list" element={<ShellRoute><UsersPage /></ShellRoute>} />
                <Route path="/users-create" element={<ShellRoute><UsersPage createOnLoad /></ShellRoute>} />
                <Route path="/users/:id" element={<ShellRoute><UsersPage /></ShellRoute>} />
                <Route path="/roles-list" element={<ShellRoute><RolesPage /></ShellRoute>} />
                <Route path="/roles/:id" element={<ShellRoute><RolesPage /></ShellRoute>} />
                <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Routes>
        </BrowserRouter>
    );
}

const rootElement = document.getElementById('app');

if (!rootElement) {
    throw new Error('No se encontró el elemento raíz #app.');
}

createRoot(rootElement).render(
    <StrictMode>
        <AppErrorBoundary>
            <App />
        </AppErrorBoundary>
    </StrictMode>,
);
