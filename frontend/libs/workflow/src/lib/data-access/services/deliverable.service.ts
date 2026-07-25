// libs/workflow/src/lib/data-access/services/deliverable.service.ts

import { inject, Injectable, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';

import { 
  Deliverable, 
  DeliverableResponse, 
  DeliverablesListResponse,
  ActionResponse 
} from '../models/deliverable.model';

@Injectable({
  providedIn: 'root'
})
export class DeliverableService {
  private http = inject(HttpClient);
  private globalApiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  
  public refreshDashboard$ = new Subject<void>();
  public refreshDeliverables$ = new Subject<void>();
  
  private workflowReqApiUrl = `${this.globalApiUrl}/workflow/requirements`;
  private workflowDeliverablesApiUrl = `${this.globalApiUrl}/workflow/deliverables`;

  public getDeliverables(requirementId: string): Observable<DeliverablesListResponse> {
    return this.http.get<DeliverablesListResponse>(`${this.workflowReqApiUrl}/${requirementId}/deliverables`);
  }

  public createDeliverable(requirementId: string, payload: Partial<Deliverable>): Observable<DeliverableResponse> {
    return this.http.post<DeliverableResponse>(`${this.workflowReqApiUrl}/${requirementId}/deliverables`, payload);
  }

  public updateDeliverable(deliverableId: string, payload: Partial<Deliverable>): Observable<DeliverableResponse> {
    return this.http.put<DeliverableResponse>(`${this.workflowDeliverablesApiUrl}/${deliverableId}`, payload);
  }

  public deleteDeliverable(deliverableId: string): Observable<ActionResponse> {
    return this.http.delete<ActionResponse>(`${this.workflowDeliverablesApiUrl}/${deliverableId}`);
  }

  
}
