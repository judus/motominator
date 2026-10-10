import { Route, Routes } from "react-router";
import { Text } from "@mantine/core";
import { Page } from "../ui/Page";
import { GarageOverview, MotorcycleList, NewMotorcycle } from "./GaragePages";
import {
  MotorcycleLayout,
  MotorcycleOverview,
  EditMotorcycle,
} from "./MotorcyclePages";
import {
  MaintenanceList,
  NewMaintenance,
  MaintenanceDetails,
  EditMaintenance,
} from "./MaintenancePages";
import {
  InvoiceListPage,
  InvoiceUploadPage,
  InvoiceReviewPage,
} from "../invoices/InvoicePages";

export function Garage({ verified }: { verified: boolean }) {
  return (
    <Routes>
      <Route index element={<GarageOverview verified={verified} />} />
      <Route
        path="motorcycles"
        element={<MotorcycleList verified={verified} />}
      />
      <Route
        path="motorcycles/new"
        element={<NewMotorcycle verified={verified} />}
      />
      <Route
        path="motorcycles/:motorcycleId"
        element={<MotorcycleLayout verified={verified} />}
      >
        <Route index element={<MotorcycleOverview />} />
        <Route path="edit" element={<EditMotorcycle />} />
        <Route path="maintenance" element={<MaintenanceList />} />
        <Route path="maintenance/new" element={<NewMaintenance />} />
        <Route path="maintenance/:recordId" element={<MaintenanceDetails />} />
        <Route
          path="maintenance/:recordId/edit"
          element={<EditMaintenance />}
        />
        <Route path="invoices" element={<InvoiceListPage />} />
        <Route path="invoices/upload" element={<InvoiceUploadPage />} />
        <Route path="invoices/:invoiceId" element={<InvoiceReviewPage />} />
      </Route>
      <Route
        path="*"
        element={
          <Page
            title="Page not found"
            parent={{ to: "/garage", label: "garage" }}
          >
            <Text>This garage page does not exist.</Text>
          </Page>
        }
      />
    </Routes>
  );
}
